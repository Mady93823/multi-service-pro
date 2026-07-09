<?php

namespace App\Listeners;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Notifications\BookingStatusNotification;

/**
 * Notify the customer when their booking reaches a status they care about
 * (M11). The notification is itself queued + after-commit; this listener only
 * decides who hears about which transitions.
 */
class SendBookingStatusNotification
{
    /**
     * Transitions worth a customer notification — the noisy interim states
     * (searching, placed) are skipped.
     *
     * @var list<BookingStatus>
     */
    private const NOTIFY_ON = [
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
        BookingStatus::Completed,
        BookingStatus::CancelledProvider,
        BookingStatus::CancelledAdmin,
    ];

    public function handle(BookingStatusChanged $event): void
    {
        if (! in_array($event->to, self::NOTIFY_ON, true)) {
            return;
        }

        $customer = $event->booking->customer;

        $customer?->notify(new BookingStatusNotification($event->booking, $event->to));
    }
}
