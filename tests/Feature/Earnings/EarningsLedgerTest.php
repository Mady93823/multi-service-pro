<?php

use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Earnings\Actions\ReverseBookingEarning;
use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\EarningType;
use App\Domain\Payments\Actions\RefundBookingToWallet;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Earning;
use Tests\Support\EarningsFixtures;

/** The invariant every ledger row must satisfy: net = gross − commission − collected. */
function assertEarningReconciles(Earning $earning): void
{
    $expected = round((float) $earning->gross - (float) $earning->commission - (float) $earning->collected_amount, 2);

    expect(round((float) $earning->net, 2))->toBe($expected);
}

function jobEarningFor(Booking $booking): Earning
{
    return Earning::query()->where('booking_id', $booking->id)->where('type', EarningType::Job->value)->sole();
}

beforeEach(function () {
    app(SettingsRegistry::class)->set('payments.commission_percent', '20');
});

test('an online job leaves the provider the job value minus commission', function () {
    $earning = jobEarningFor(EarningsFixtures::complete(EarningsFixtures::booking()));

    expect($earning->gross)->toBe('500.00')
        ->and($earning->commission)->toBe('100.00')
        ->and($earning->collected_amount)->toBe('0.00')
        ->and($earning->net)->toBe('400.00');

    assertEarningReconciles($earning);
});

test('a cash job leaves the provider owing the commission and the tax they pocketed', function () {
    $booking = EarningsFixtures::complete(EarningsFixtures::booking(PaymentMethod::Cash));
    $earning = jobEarningFor($booking);

    // The provider took 590 at the door on a 500 job whose commission is 100,
    // so 100 commission + 90 GST goes back to the platform.
    expect($earning->collected_amount)->toBe('590.00')
        ->and($earning->net)->toBe('-190.00')
        ->and($booking->fresh()->provider_earning)->toBe('-190.00');

    assertEarningReconciles($earning);
});

test('a positive earning waits out the hold window', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 7);

    $earning = jobEarningFor(EarningsFixtures::complete(EarningsFixtures::booking()));

    expect($earning->status)->toBe(EarningStatus::Pending)
        ->and($earning->available_at->isAfter(now()->addDays(6)))->toBeTrue();
});

test('a zero-day hold releases an earning immediately', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 0);

    expect(jobEarningFor(EarningsFixtures::complete(EarningsFixtures::booking()))->status)->toBe(EarningStatus::Available);
});

test('a debt never waits out the hold window', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 30);

    $earning = jobEarningFor(EarningsFixtures::complete(EarningsFixtures::booking(PaymentMethod::Cash)));

    expect($earning->status)->toBe(EarningStatus::Available);
});

test('earnings:release opens the earnings whose hold has passed', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 7);
    $earning = jobEarningFor(EarningsFixtures::complete(EarningsFixtures::booking()));

    $this->artisan('earnings:release')->assertSuccessful();
    expect($earning->fresh()->status)->toBe(EarningStatus::Pending);

    $earning->forceFill(['available_at' => now()->subMinute()])->save();

    $this->artisan('earnings:release')->assertSuccessful();
    expect($earning->fresh()->status)->toBe(EarningStatus::Available);
});

test('a refund appends a reversal that cancels the earning out', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 0);

    $booking = EarningsFixtures::markPaid(EarningsFixtures::complete(EarningsFixtures::booking()));

    app(RefundBookingToWallet::class)->handle($booking);

    $rows = Earning::query()->where('booking_id', $booking->id)->get();
    $reversal = $rows->firstWhere('type', EarningType::Reversal);

    expect($rows)->toHaveCount(2)
        ->and($reversal->gross)->toBe('-500.00')
        ->and($reversal->commission)->toBe('-100.00')
        ->and($reversal->net)->toBe('-400.00')
        ->and(round((float) $rows->sum(fn (Earning $row): float => (float) $row->net), 2))->toBe(0.0);

    assertEarningReconciles($reversal);
});

test('refunding a cash job forgives the commission the provider owed', function () {
    $booking = EarningsFixtures::complete(EarningsFixtures::booking(PaymentMethod::Cash));

    // SettleCashOnCompletion already captured the cash payment on completion.
    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    app(RefundBookingToWallet::class)->handle($booking->fresh());

    $reversal = Earning::query()->where('booking_id', $booking->id)->where('type', EarningType::Reversal->value)->sole();

    expect($reversal->net)->toBe('190.00');
    assertEarningReconciles($reversal);
});

test('a replayed reversal does not append a second row', function () {
    app(SettingsRegistry::class)->set('payouts.hold_days', 0);
    $booking = EarningsFixtures::markPaid(EarningsFixtures::complete(EarningsFixtures::booking()));

    app(RefundBookingToWallet::class)->handle($booking);
    app(ReverseBookingEarning::class)->handle($booking->fresh());

    expect(Earning::query()->where('booking_id', $booking->id)->where('type', EarningType::Reversal->value)->count())->toBe(1);
});

test('a refunded booking that never completed reverses nothing', function () {
    $booking = EarningsFixtures::markPaid(Booking::factory()->create());

    app(RefundBookingToWallet::class)->handle($booking);

    expect(Earning::query()->where('booking_id', $booking->id)->count())->toBe(0);
});
