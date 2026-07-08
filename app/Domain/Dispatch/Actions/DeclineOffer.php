<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Models\DispatchOffer;
use App\Models\User;

/**
 * A provider declines a dispatch offer (M06). When no offer for the booking is
 * still open, dispatch immediately looks for the next candidate (the next
 * nearest, or the next round for broadcast) — the pending timeout job will then
 * no-op for the fully-responded round.
 */
class DeclineOffer
{
    public function __construct(private readonly DispatchBooking $dispatch) {}

    public function handle(DispatchOffer $offer, User $provider): void
    {
        if ((int) $offer->provider_id !== $provider->id) {
            abort(403);
        }

        if (! $offer->status->isOpen()) {
            return; // already resolved — nothing to do.
        }

        $offer->update([
            'status' => OfferStatus::Declined->value,
            'responded_at' => now(),
        ]);

        $booking = $offer->booking()->first();

        if ($booking === null || $booking->status !== BookingStatus::Searching) {
            return;
        }

        $openRemaining = DispatchOffer::query()
            ->where('booking_id', $booking->id)
            ->where('status', OfferStatus::Offered->value)
            ->exists();

        if (! $openRemaining) {
            $this->dispatch->handle($booking);
        }
    }
}
