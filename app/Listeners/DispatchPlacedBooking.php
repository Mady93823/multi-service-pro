<?php

namespace App\Listeners;

use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Dispatch\Actions\DispatchBooking;
use App\Domain\Settings\SettingsRegistry;

/**
 * Auto-dispatch (M06): as soon as a booking is placed, start the first
 * dispatch round. Runs synchronously so offers exist by the time the customer
 * lands on the booking page; the offer timeout is the only queued piece.
 * Turn off with the dispatch.auto setting for admin-only assignment.
 */
class DispatchPlacedBooking
{
    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly DispatchBooking $dispatch,
    ) {}

    public function handle(BookingPlaced $event): void
    {
        if (! $this->settings->boolean('dispatch.auto', true)) {
            return;
        }

        $this->dispatch->handle($event->booking);
    }
}
