<?php

namespace App\Domain\Tracking\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Tracking\Enums\TrackingSessionStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Provider taps "Arrived": end the live session and move en_route → arrived.
 * The map freezes to a summary; the point trail stays in tracking_points.
 */
class EndTrackingSession
{
    public function __construct(private readonly BookingStateMachine $machine) {}

    public function handle(Booking $booking, User $provider): Booking
    {
        return DB::transaction(function () use ($booking, $provider): Booking {
            $booking->trackingSessions()
                ->where('status', TrackingSessionStatus::Active->value)
                ->update([
                    'status' => TrackingSessionStatus::Ended->value,
                    'ended_at' => now(),
                ]);

            if ($booking->status === BookingStatus::EnRoute) {
                $this->machine->transition($booking, BookingStatus::Arrived, BookingActor::Provider, $provider);
            }

            return $booking->refresh();
        });
    }
}
