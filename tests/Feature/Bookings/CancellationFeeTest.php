<?php

use App\Domain\Bookings\CancellationFeeCalculator;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use Carbon\CarbonImmutable;

function fees(): CancellationFeeCalculator
{
    return app(CancellationFeeCalculator::class);
}

test('cancelling before the free window costs nothing', function () {
    $booking = Booking::factory()->scheduledAt(CarbonImmutable::now()->addHours(48))->create();

    expect(fees()->feeFor($booking))->toBe('0.00');
});

test('cancelling inside the window charges the percent fee', function () {
    // Defaults: free until 2h before, then 10% of the 590.00 total.
    $booking = Booking::factory()->scheduledAt(CarbonImmutable::now()->addHour())->create();

    expect(fees()->feeFor($booking))->toBe('59.00');
});

test('flat fees come from settings', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('booking.cancellation_fee_type', 'flat');
    $settings->set('booking.cancellation_fee_value', '100');

    $booking = Booking::factory()->scheduledAt(CarbonImmutable::now()->addHour())->create();

    expect(fees()->feeFor($booking))->toBe('100.00');
});

test('the fee never exceeds the booking total', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('booking.cancellation_fee_type', 'flat');
    $settings->set('booking.cancellation_fee_value', '10000');

    $booking = Booking::factory()->scheduledAt(CarbonImmutable::now()->addHour())->create();

    expect(fees()->feeFor($booking))->toBe('590.00');
});
