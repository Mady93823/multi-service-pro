<?php

namespace App\Listeners;

use App\Domain\Earnings\Actions\ReverseBookingEarning;
use App\Domain\Payments\Events\BookingRefunded;

/**
 * Auto-discovered. A booking with no earning (never completed, so never
 * credited) reverses to nothing — the action returns null.
 */
class ReverseEarningOnRefund
{
    public function __construct(private readonly ReverseBookingEarning $action) {}

    public function handle(BookingRefunded $event): void
    {
        $this->action->handle(
            $event->booking,
            __('Reversed: booking :code was refunded', ['code' => $event->booking->code]),
        );
    }
}
