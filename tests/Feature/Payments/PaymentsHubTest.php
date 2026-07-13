<?php

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/** The seeder completes a demo booking, so payments rows exist before we start. */
function hubPayment(PaymentProvider $gateway, PaymentState $status, float $amount = 500.0): Payment
{
    $booking = Booking::factory()->create();

    /** @var Payment $payment */
    $payment = $booking->payments()->create([
        'gateway' => $gateway,
        'gateway_ref' => $gateway->isOnlineGateway() ? 'ref_'.uniqid() : null,
        'amount' => $amount,
        'currency' => 'INR',
        'status' => $status,
        'captured_at' => $status === PaymentState::Captured ? now() : null,
    ]);

    return $payment;
}

test('the payments hub lists payments and totals only what the filters asked for', function () {
    $admin = User::factory()->admin()->create();

    hubPayment(PaymentProvider::Razorpay, PaymentState::Captured, 1000);
    hubPayment(PaymentProvider::Razorpay, PaymentState::Refunded, 250);
    hubPayment(PaymentProvider::Offline, PaymentState::Initiated, 700);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['gateway' => 'razorpay']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/payments/index')
            ->where('totals.count', 2)
            ->where('totals.captured', fn ($value): bool => (float) $value === 1000.0)
            ->where('totals.refunded', fn ($value): bool => (float) $value === 250.0)
            // The awaiting tile is a queue depth, not a filtered figure.
            ->where('totals.awaiting', 1)
            ->has('payments.data', 2)
            ->has('payments.meta.last_page'),
        );
});

test('the awaiting-verification filter finds the offline queue', function () {
    $admin = User::factory()->admin()->create();

    hubPayment(PaymentProvider::Offline, PaymentState::Initiated);
    hubPayment(PaymentProvider::Cash, PaymentState::Captured);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['gateway' => 'offline', 'status' => 'initiated']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.gateway', 'offline')
            ->where('payments.data.0.status', 'initiated'),
        );
});

test('a verification and a rejection are both audited', function () {
    $admin = User::factory()->admin()->create();
    app(SettingsRegistry::class)->set('payments.offline_enabled', true);

    $verified = hubPayment(PaymentProvider::Offline, PaymentState::Initiated);
    $rejected = hubPayment(PaymentProvider::Offline, PaymentState::Initiated);

    $this->actingAs($admin)->post(route('admin.payments.verify', $verified))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.payments.reject', $rejected), ['reason' => 'Not received.'])->assertRedirect();

    expect(ActivityLog::query()->where('action', 'payment.verify')->where('subject_id', $verified->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'payment.reject')->where('subject_id', $rejected->id)->exists())->toBeTrue();
});

test('the hub is admin-only', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.payments.index'))
        ->assertForbidden();
});

test('an account with payments against it cannot be deleted, only deactivated', function () {
    $admin = User::factory()->admin()->create();
    $account = BankAccount::factory()->create();
    $booking = Booking::factory()->create();

    $booking->payments()->create([
        'gateway' => PaymentProvider::Offline,
        'bank_account_id' => $account->id,
        'amount' => 100,
        'currency' => 'INR',
        'status' => PaymentState::Captured,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.bank-accounts.destroy', $account))
        ->assertSessionHasErrors('bank_account');

    expect(BankAccount::query()->whereKey($account->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.bank-accounts.update', $account), [
            'label' => $account->label,
            'upi_id' => $account->upi_id,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($account->refresh()->is_active)->toBeFalse();
});

test('a bank account needs a bank number or a UPI id', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bank-accounts.store'), ['label' => 'Empty account'])
        ->assertSessionHasErrors('account_number');

    expect(BankAccount::query()->where('label', 'Empty account')->exists())->toBeFalse();
});

test('an unused bank account can be deleted', function () {
    $admin = User::factory()->admin()->create();
    $account = BankAccount::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.bank-accounts.destroy', $account))
        ->assertRedirect();

    expect(BankAccount::query()->whereKey($account->id)->exists())->toBeFalse();
});
