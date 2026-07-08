<?php

namespace App\Domain\Bookings;

use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * Cancellation fee (M04, UC parity): cancelling earlier than
 * booking.free_cancel_hours before the slot is free; after that a flat or
 * percent fee applies (booking.cancellation_fee_type / _value), capped at
 * the booking total. The result is snapshotted to bookings.cancellation_fee
 * at cancel time — later settings changes never rewrite history.
 */
class CancellationFeeCalculator
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return string decimal amount, e.g. "99.00"
     */
    public function feeFor(Booking $booking, ?CarbonImmutable $at = null): string
    {
        $at ??= CarbonImmutable::now();
        $freeUntil = $booking->scheduled_at->toImmutable()
            ->subHours($this->settings->integer('booking.free_cancel_hours', 2));

        if ($at->lessThanOrEqualTo($freeUntil)) {
            return '0.00';
        }

        $total = (float) $booking->total;
        $value = $this->settings->decimal('booking.cancellation_fee_value', 0.0);

        $fee = match ($this->settings->string('booking.cancellation_fee_type', 'percent')) {
            'flat' => $value,
            default => $total * $value / 100,
        };

        return number_format(min(max($fee, 0), $total), 2, '.', '');
    }
}
