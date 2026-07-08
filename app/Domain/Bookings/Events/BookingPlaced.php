<?php

namespace App\Domain\Bookings\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

class BookingPlaced
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking) {}
}
