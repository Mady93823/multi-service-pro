<?php

namespace App\Listeners;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;

/**
 * Pay-after-service settlement (M08): the provider collects cash at the door,
 * so completing the job is the moment the booking becomes paid — recorded as
 * a captured cash payment row so ledgers reconcile.
 *
 * Auto-discovered from the handle() type-hint — do not also register it in a
 * provider (the M06 double-fire lesson).
 */
class SettleCashOnCompletion
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking;

        if ($event->to !== BookingStatus::Completed
            || $booking->payment_method !== PaymentMethod::Cash
            || $booking->payment_status !== PaymentStatus::Unpaid) {
            return;
        }

        $booking->payments()->create([
            'gateway' => PaymentProvider::Cash,
            'amount' => $booking->total,
            'currency' => $this->settings->string('localization.currency', 'INR') ?: 'INR',
            'status' => PaymentState::Captured,
            'captured_at' => now(),
        ]);

        $booking->payment_status = PaymentStatus::Paid;
        $booking->save();
    }
}
