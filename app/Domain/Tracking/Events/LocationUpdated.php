<?php

namespace App\Domain\Tracking\Events;

use App\Models\Booking;
use App\Models\TrackingSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A fresh, accepted GPS checkpoint. Broadcast immediately (not queued) —
 * latency is the whole point of live tracking (05-Live-Tracking).
 */
class LocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Booking $booking,
        public readonly TrackingSession $session,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tracking.booking.'.$this->booking->id)];
    }

    public function broadcastAs(): string
    {
        return 'LocationUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'lat' => (float) $this->session->last_lat,
            'lng' => (float) $this->session->last_lng,
            'heading' => $this->session->last_heading === null ? null : (float) $this->session->last_heading,
            'speed' => $this->session->last_speed_kmh === null ? null : (float) $this->session->last_speed_kmh,
            'accuracy' => $this->session->last_accuracy_m === null ? null : (float) $this->session->last_accuracy_m,
            'ts' => $this->session->last_ping_at?->toIso8601String(),
        ];
    }
}
