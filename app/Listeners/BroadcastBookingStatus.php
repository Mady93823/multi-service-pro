<?php

namespace App\Listeners;

use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Domain\Tracking\Events\BookingStatusBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mirror every status change onto the booking's tracking channel so an open
 * customer map reacts live (M07). Queued + after-commit so the broadcast fires
 * once the transaction is durable, never inside the state machine's write.
 */
class BroadcastBookingStatus implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(BookingStatusChanged $event): void
    {
        BookingStatusBroadcast::dispatch($event->booking, $event->to);
    }
}
