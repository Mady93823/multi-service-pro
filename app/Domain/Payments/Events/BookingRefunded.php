<?php

namespace App\Domain\Payments\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money went back to the customer (M08). M09 listens here to reverse the
 * provider's earning — payments code never imports the earnings domain
 * (07-Conventions events rule).
 */
class BookingRefunded
{
    use Dispatchable;

    public function __construct(
        public readonly Booking $booking,
        public readonly float $amount,
    ) {}
}
