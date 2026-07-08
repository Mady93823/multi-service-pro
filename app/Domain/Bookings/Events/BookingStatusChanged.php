<?php

namespace App\Domain\Bookings\Events;

use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every state-machine transition. Cross-module effects (M11
 * notifications, M09 earnings on completion, ...) listen here — booking
 * code never imports them (07-Conventions events rule).
 */
class BookingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $from,
        public readonly BookingStatus $to,
        public readonly BookingActor $actor,
    ) {}
}
