<?php

namespace App\Domain\Tracking\Actions;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\Tracking\Enums\TrackingSessionStatus;
use App\Domain\Tracking\Events\LocationUpdated;
use App\Domain\Tracking\GeoPing;
use App\Models\Booking;
use App\Models\TrackingSession;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Validate → persist → broadcast a single GPS ping (05-Live-Tracking server
 * internals). The route already checks the actor is the booking's provider;
 * here we enforce accuracy and an open session, then push to the customer.
 */
class RecordTrackingPing
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array{session: TrackingSession, dropped: bool}
     */
    public function handle(Booking $booking, GeoPing $ping): array
    {
        $session = $booking->trackingSessions()
            ->where('status', TrackingSessionStatus::Active->value)
            ->latest('id')
            ->firstOrFail();

        // Drop jittery fixes rather than smearing the marker across the map.
        $maxAccuracy = $this->settings->integer('tracking.max_accuracy_meters', 100);
        if ($ping->accuracy !== null && $ping->accuracy > $maxAccuracy) {
            return ['session' => $session, 'dropped' => true];
        }

        $recordedAt = $ping->recordedAt();

        $session->points()->create([
            'lat' => $ping->lat,
            'lng' => $ping->lng,
            'accuracy_m' => $ping->accuracy,
            'speed_kmh' => $ping->speed,
            'heading' => $ping->heading,
            'recorded_at' => $recordedAt,
        ]);

        $session->forceFill([
            'last_lat' => $ping->lat,
            'last_lng' => $ping->lng,
            'last_accuracy_m' => $ping->accuracy,
            'last_heading' => $ping->heading,
            'last_speed_kmh' => $ping->speed,
            'last_ping_at' => $recordedAt,
        ])->save();

        // The checkpoint is already durable, so a dead Reverb must not fail the
        // provider's ping: tracking degrades to the customer's polling fallback
        // and the job carries on (05-Live-Tracking failure modes).
        try {
            LocationUpdated::dispatch($booking, $session);
        } catch (BroadcastException $exception) {
            Log::warning('Tracking broadcast failed; falling back to polling.', [
                'booking_id' => $booking->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return ['session' => $session, 'dropped' => false];
    }
}
