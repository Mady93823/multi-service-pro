<?php

use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Earnings\Actions\ProcessPayout;
use App\Domain\Earnings\Actions\RequestPayout;
use App\Domain\Earnings\Actions\ReverseBookingEarning;
use App\Domain\Earnings\EarningsLedger;
use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\Support\EarningsFixtures;

const UPI = ['method' => 'upi', 'upi_id' => 'pro@upi'];

/** An approved provider sitting on `$jobs` released online earnings of 400 each. */
function payoutProvider(int $jobs = 1): User
{
    $provider = User::factory()->provider()->create();
    ProviderProfile::factory()->approved()->online()->for($provider)->create();

    for ($i = 0; $i < $jobs; $i++) {
        EarningsFixtures::complete(EarningsFixtures::booking(provider: $provider));
    }

    return $provider;
}

beforeEach(function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.commission_percent', '20');
    // Released straight away: the hold window has its own tests.
    $settings->set('payouts.hold_days', 0);
    $settings->set('payouts.min_amount', '100');
    $settings->set('payouts.enabled', true);
});

test('a payout claims the whole released balance and the earnings that fund it', function () {
    $provider = payoutProvider(jobs: 2);

    $payout = app(RequestPayout::class)->handle($provider, UPI);

    expect($payout->amount)->toBe('800.00')
        ->and($payout->status)->toBe(PayoutStatus::Requested)
        ->and($payout->earnings()->count())->toBe(2)
        ->and(app(EarningsLedger::class)->claimable($provider))->toBe(0.0);
});

test('a cash-job debt offsets the payout rather than being left behind', function () {
    $provider = payoutProvider(jobs: 2);
    EarningsFixtures::complete(EarningsFixtures::booking(PaymentMethod::Cash, provider: $provider));

    // 400 + 400 − 190.
    expect(app(RequestPayout::class)->handle($provider, UPI)->amount)->toBe('610.00');
});

test('a provider who owes more than they earned cannot request a payout', function () {
    $provider = User::factory()->provider()->create();
    EarningsFixtures::complete(EarningsFixtures::booking(PaymentMethod::Cash, provider: $provider));

    expect(app(EarningsLedger::class)->claimable($provider))->toBe(-190.0);

    app(RequestPayout::class)->handle($provider, UPI);
})->throws(ValidationException::class, 'You have no balance available to withdraw.');

test('a payout below the minimum is refused', function () {
    app(SettingsRegistry::class)->set('payouts.min_amount', '500');

    app(RequestPayout::class)->handle(payoutProvider(), UPI);
})->throws(ValidationException::class);

test('a second payout cannot claim earnings an open one already holds', function () {
    $provider = payoutProvider();
    app(RequestPayout::class)->handle($provider, UPI);

    app(RequestPayout::class)->handle($provider, UPI);
})->throws(ValidationException::class, 'You already have a payout request in progress.');

test('payouts can be switched off entirely', function () {
    app(SettingsRegistry::class)->set('payouts.enabled', false);

    app(RequestPayout::class)->handle(payoutProvider(), UPI);
})->throws(ValidationException::class, 'Payouts are currently disabled.');

test('approving then paying settles the claimed earnings', function () {
    $provider = payoutProvider();
    $admin = User::factory()->admin()->create();
    $payout = app(RequestPayout::class)->handle($provider, UPI);

    app(ProcessPayout::class)->approve($payout, $admin);
    expect($payout->fresh()->status)->toBe(PayoutStatus::Approved);

    app(ProcessPayout::class)->markPaid($payout, $admin, 'UTR12345');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->fresh()->reference)->toBe('UTR12345')
        ->and($payout->fresh()->processed_by)->toBe($admin->id)
        ->and(Earning::query()->where('provider_id', $provider->id)->sole()->status)->toBe(EarningStatus::PaidOut)
        ->and(app(EarningsLedger::class)->claimable($provider))->toBe(0.0);
});

test('rejecting a payout hands the earnings back so the provider can try again', function () {
    $provider = payoutProvider();
    $payout = app(RequestPayout::class)->handle($provider, UPI);

    app(ProcessPayout::class)->reject($payout, User::factory()->admin()->create(), 'Wrong UPI id.');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->fresh()->note)->toBe('Wrong UPI id.')
        ->and(Earning::query()->where('provider_id', $provider->id)->sole()->payout_request_id)->toBeNull()
        ->and(app(EarningsLedger::class)->claimable($provider))->toBe(400.0);

    expect(app(RequestPayout::class)->handle($provider, UPI)->amount)->toBe('400.00');
});

test('a settled payout cannot be processed twice', function () {
    $admin = User::factory()->admin()->create();
    $payout = app(RequestPayout::class)->handle(payoutProvider(), UPI);
    app(ProcessPayout::class)->markPaid($payout, $admin, 'UTR1');

    app(ProcessPayout::class)->markPaid($payout->fresh(), $admin, 'UTR2');
})->throws(ValidationException::class, 'This payout request has already been processed.');

test('reversing a paid-out earning leaves the correction on the next balance', function () {
    $provider = payoutProvider();
    $payout = app(RequestPayout::class)->handle($provider, UPI);
    app(ProcessPayout::class)->markPaid($payout, User::factory()->admin()->create(), 'UTR1');

    $booking = Earning::query()->where('provider_id', $provider->id)->sole()->booking;
    app(ReverseBookingEarning::class)->handle($booking);

    // Money already left the platform, so the reversal has to bite the next payout.
    expect(app(EarningsLedger::class)->claimable($provider))->toBe(-400.0);
});

test('the provider earnings screen renders for an approved provider', function () {
    $provider = payoutProvider();

    $this->actingAs($provider)
        ->get(route('provider.earnings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('provider/earnings')
            // A closure, not a literal: the prop crosses JSON, where 400.0 loses its decimal.
            ->where('summary.claimable', fn (int|float $claimable): bool => (float) $claimable === 400.0)
            ->where('has_open_payout', false));
});

test('a provider requests a payout from their earnings screen', function () {
    $provider = payoutProvider();

    $this->actingAs($provider)
        ->post(route('provider.payouts.store'), UPI)
        ->assertRedirect();

    expect(PayoutRequest::query()->where('provider_id', $provider->id)->sole()->method_details)
        ->toBe(['method' => 'upi', 'upi_id' => 'pro@upi']);
});

test('bank details are required when the provider picks a bank transfer', function () {
    $provider = payoutProvider();

    $this->actingAs($provider)
        ->post(route('provider.payouts.store'), ['method' => 'bank'])
        ->assertSessionHasErrors(['account_name', 'account_number', 'ifsc']);
});

test('a upi payout never stores a half-filled bank block', function () {
    $provider = payoutProvider();

    $this->actingAs($provider)->post(route('provider.payouts.store'), [
        ...UPI,
        'account_number' => '000111222',
    ])->assertRedirect();

    expect(PayoutRequest::query()->where('provider_id', $provider->id)->sole()->method_details)
        ->not->toHaveKey('account_number');
});

test('the admin payout queue lists requests and only admins may see it', function () {
    $provider = payoutProvider();
    app(RequestPayout::class)->handle($provider, UPI);

    $this->actingAs($provider)->get(route('admin.payouts.index'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.payouts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/payouts/index')->has('payouts.data', 1));
});

test('marking a payout paid from the admin screen requires a reference', function () {
    $payout = app(RequestPayout::class)->handle(payoutProvider(), UPI);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.payouts.pay', $payout->id), [])
        ->assertSessionHasErrors('reference');
});
