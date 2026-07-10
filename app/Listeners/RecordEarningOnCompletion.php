<?php

namespace App\Listeners;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Domain\Earnings\Actions\RecordBookingEarning;

/**
 * Completion is the moment commission is resolved and snapshotted (M09) — a
 * later rate change must never rewrite this booking's split.
 *
 * Auto-discovered from the handle() type-hint. Do not also register it in a
 * provider (the M06 double-fire lesson).
 */
class RecordEarningOnCompletion
{
    public function __construct(private readonly RecordBookingEarning $action) {}

    public function handle(BookingStatusChanged $event): void
    {
        if ($event->to !== BookingStatus::Completed) {
            return;
        }

        $this->action->handle($event->booking);
    }
}
