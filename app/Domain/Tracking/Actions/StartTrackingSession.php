<?php

namespace App\Domain\Tracking\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Tracking\Enums\TrackingSessionStatus;
use App\Models\Booking;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Provider taps "Start Journey": move the booking accepted → en_route and open
 * a tracking session. Idempotent — a page refresh mid-journey re-uses the
 * live session instead of stacking a second one (05-Live-Tracking).
 */
class StartTrackingSession
{
    public function __construct(private readonly BookingStateMachine $machine) {}

    public function handle(Booking $booking, User $provider): TrackingSession
    {
        return DB::transaction(function () use ($booking, $provider): TrackingSession {
            $existing = $booking->trackingSessions()
                ->where('status', TrackingSessionStatus::Active->value)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($booking->status === BookingStatus::Accepted) {
                $this->machine->transition($booking, BookingStatus::EnRoute, BookingActor::Provider, $provider);
            }

            return $booking->trackingSessions()->create([
                'provider_id' => $provider->id,
                'status' => TrackingSessionStatus::Active,
                'started_at' => now(),
            ]);
        });
    }
}
