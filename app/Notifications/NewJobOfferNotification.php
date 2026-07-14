<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\Booking;

/**
 * A dispatch offer reached this provider (M06 → M11). Drives the Jobs badge
 * and a live toast so an online provider sees offers without refreshing.
 *
 * Email is off by default for this event (M23): an offer expires in a minute,
 * so push and SMS are the only channels that can actually catch it.
 */
class NewJobOfferNotification extends PlatformNotification
{
    public function __construct(public readonly Booking $booking)
    {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::JobOffer;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_offer',
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'title' => __('New job offer'),
            'body' => __('A new booking near you is available — respond before it expires.'),
            'url' => route('provider.jobs.index'),
        ];
    }
}
