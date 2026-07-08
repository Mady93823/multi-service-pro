<?php

namespace App\Domain\Dispatch\Jobs;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Actions\DispatchBooking;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Models\Booking;
use App\Models\DispatchOffer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fires when a dispatch round's offer window closes (M06). Expires any offers
 * still open in that round and re-dispatches the next batch. If nothing was
 * still open, a decline already drove the next round — this no-ops so the two
 * paths never double-dispatch.
 *
 * The `expires_at` guard keeps this correct regardless of queue driver: on a
 * `sync` queue the job runs the instant it is dispatched (delay ignored), so it
 * must refuse to expire offers whose window has not actually elapsed. On a real
 * worker the delay lands it right at the deadline and the guard passes.
 */
class ExpireDispatchRound implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $round,
    ) {}

    public function handle(DispatchBooking $dispatch): void
    {
        $booking = Booking::query()->find($this->bookingId);

        if ($booking === null || $booking->status !== BookingStatus::Searching) {
            return; // accepted, cancelled or otherwise moved on.
        }

        $expired = DispatchOffer::query()
            ->where('booking_id', $this->bookingId)
            ->where('round', $this->round)
            ->where('status', OfferStatus::Offered->value)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => OfferStatus::Expired->value,
                'responded_at' => now(),
            ]);

        if ($expired === 0) {
            return; // window not elapsed yet, or a decline already advanced it.
        }

        $dispatch->handle($booking);
    }
}
