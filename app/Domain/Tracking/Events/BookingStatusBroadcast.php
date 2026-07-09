<?php

namespace App\Domain\Tracking\Events;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Booking status changes pushed onto the tracking channel so the customer's
 * live map reacts (arrived → freeze, completed → summary) without a reload.
 * Separate from the M11 user-notification path.
 */
class BookingStatusBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $status,
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
        return 'BookingStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->status->value,
        ];
    }
}
