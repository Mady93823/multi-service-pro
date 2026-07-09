<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Tracking\Actions\EndTrackingSession;
use App\Domain\Tracking\Actions\RecordTrackingPing;
use App\Domain\Tracking\Actions\StartTrackingSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\RecordPingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Provider side of live tracking (05-Live-Tracking). The journey screen is
 * Inertia; the ping/start/stop endpoints answer JSON so the GPS loop never
 * pays for a full page response.
 */
class TrackingController extends Controller
{
    /**
     * Statuses where a journey screen still makes sense.
     *
     * @var list<BookingStatus>
     */
    private const JOURNEY_STATUSES = [BookingStatus::Accepted, BookingStatus::EnRoute];

    public function journey(Request $request, Booking $booking, SettingsRegistry $settings): Response|RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        abort_unless($booking->provider_id === $provider->id, 404);

        if (! in_array($booking->status, self::JOURNEY_STATUSES, true)) {
            return redirect()->route('provider.jobs.index');
        }

        $booking->load(['items', 'customer', 'zone']);
        $session = $booking->trackingSessions()->active()->latest('id')->first();

        return Inertia::render('provider/journey', [
            'booking' => new BookingResource($booking),
            'session' => $session === null ? null : [
                'status' => $session->status->value,
                'last_lat' => $session->last_lat === null ? null : (float) $session->last_lat,
                'last_lng' => $session->last_lng === null ? null : (float) $session->last_lng,
            ],
            'config' => $this->config($settings),
        ]);
    }

    public function start(Request $request, Booking $booking, StartTrackingSession $action): JsonResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        abort_unless($booking->provider_id === $provider->id, 404);

        $session = $action->handle($booking, $provider);

        return response()->json([
            'status' => $booking->refresh()->status->value,
            'session' => ['id' => $session->id, 'status' => $session->status->value],
        ]);
    }

    public function ping(RecordPingRequest $request, Booking $booking, RecordTrackingPing $action): JsonResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        abort_unless($booking->provider_id === $provider->id, 404);
        abort_unless($booking->status === BookingStatus::EnRoute, 409, 'Booking is not on an active journey.');

        $result = $action->handle($booking, $request->toGeoPing());

        return response()->json(['ok' => true, 'dropped' => $result['dropped']]);
    }

    public function stop(Request $request, Booking $booking, EndTrackingSession $action): JsonResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        abort_unless($booking->provider_id === $provider->id, 404);

        $action->handle($booking, $provider);

        return response()->json(['status' => $booking->refresh()->status->value]);
    }

    /**
     * @return array<string, int>
     */
    private function config(SettingsRegistry $settings): array
    {
        return [
            'ping_interval_seconds' => $settings->integer('tracking.ping_interval_seconds', 3),
            'min_move_meters' => $settings->integer('tracking.min_move_meters', 5),
            'max_accuracy_meters' => $settings->integer('tracking.max_accuracy_meters', 100),
            'stale_after_seconds' => $settings->integer('tracking.stale_after_seconds', 30),
        ];
    }
}
