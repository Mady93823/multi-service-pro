<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\Review;

/**
 * Tells the provider a customer rated their job (M10). Queued so the review
 * request never waits on delivery.
 */
class ReviewReceivedNotification extends PlatformNotification
{
    public function __construct(public readonly Review $review)
    {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::ReviewReceived;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_received',
            'review_id' => $this->review->id,
            'booking_id' => $this->review->booking_id,
            'rating' => $this->review->rating,
            'title' => __('New review'),
            'body' => __('A customer rated your job :rating out of 5.', ['rating' => (string) $this->review->rating]),
            'url' => route('provider.dashboard'),
        ];
    }
}
