<?php

namespace App\Console\Commands;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use Illuminate\Console\Command;

/**
 * Close the payment window (M08): pending_payment bookings older than
 * booking.payment_timeout_minutes expire so their slots free up. A payment
 * that still lands afterwards is kept + flagged by ConfirmPayment for a
 * support refund.
 */
class ExpireUnpaidBookings extends Command
{
    protected $signature = 'bookings:expire-unpaid';

    protected $description = 'Expire bookings whose online payment window has closed';

    public function handle(BookingStateMachine $machine, SettingsRegistry $settings): int
    {
        $minutes = $settings->integer('booking.payment_timeout_minutes', 30);
        $cutoff = now()->subMinutes($minutes);

        $expired = 0;

        Booking::query()
            ->where('status', BookingStatus::PendingPayment->value)
            ->where('created_at', '<', $cutoff)
            ->each(function (Booking $booking) use ($machine, &$expired): void {
                $machine->transition(
                    $booking,
                    BookingStatus::Expired,
                    BookingActor::System,
                    null,
                    __('Payment was not completed in time.'),
                );
                $expired++;
            });

        $this->info("Expired {$expired} unpaid booking(s) older than {$minutes} minute(s).");

        return self::SUCCESS;
    }
}
