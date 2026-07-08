<?php

namespace App\Domain\Dispatch\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when no eligible provider remains (or the round cap is hit) and the
 * booking is still searching. M11 raises the admin alert; the booking stays
 * visible in `searching` for manual assignment.
 */
class DispatchExhausted
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking) {}
}
