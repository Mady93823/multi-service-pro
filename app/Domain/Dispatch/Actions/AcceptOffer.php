<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A provider accepts a dispatch offer (M06). Locks the booking so a broadcast
 * race resolves to a single winner: the booking is assigned to this provider
 * (searching → assigned → accepted) and every sibling offer is expired.
 */
class AcceptOffer
{
    public function __construct(private readonly BookingStateMachine $machine) {}

    public function handle(DispatchOffer $offer, User $provider): Booking
    {
        return DB::transaction(function () use ($offer, $provider): Booking {
            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($offer->booking_id)->lockForUpdate()->firstOrFail();
            $offer->refresh();

            if ((int) $offer->provider_id !== $provider->id) {
                abort(403);
            }

            if (! $offer->status->isOpen() || $booking->status !== BookingStatus::Searching) {
                throw ValidationException::withMessages([
                    'offer' => __('This job has already been taken.'),
                ]);
            }

            $offer->update([
                'status' => OfferStatus::Accepted->value,
                'responded_at' => now(),
            ]);

            $booking->provider_id = $provider->id;
            $booking->save();

            $this->machine->transition($booking, BookingStatus::Assigned, BookingActor::System, $provider);
            $this->machine->transition($booking, BookingStatus::Accepted, BookingActor::Provider, $provider);

            DispatchOffer::query()
                ->where('booking_id', $booking->id)
                ->where('id', '!=', $offer->id)
                ->where('status', OfferStatus::Offered->value)
                ->update([
                    'status' => OfferStatus::Expired->value,
                    'responded_at' => now(),
                ]);

            return $booking->refresh();
        });
    }
}
