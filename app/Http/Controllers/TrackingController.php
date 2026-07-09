<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Customer polling fallback (05-Live-Tracking): when Echo is disconnected the
 * map fetches the last checkpoint here every few seconds. Any party allowed to
 * view the booking (customer / assigned provider / admin) may read it.
 */
class TrackingController extends Controller
{
    public function last(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $session = $booking->trackingSessions()->latest('id')->first();

        return response()->json([
            'booking_status' => $booking->status->value,
            'session_status' => $session?->status->value,
            'lat' => $session === null || $session->last_lat === null ? null : (float) $session->last_lat,
            'lng' => $session === null || $session->last_lng === null ? null : (float) $session->last_lng,
            'heading' => $session === null || $session->last_heading === null ? null : (float) $session->last_heading,
            'speed' => $session === null || $session->last_speed_kmh === null ? null : (float) $session->last_speed_kmh,
            'ts' => $session?->last_ping_at?->toIso8601String(),
        ]);
    }
}
