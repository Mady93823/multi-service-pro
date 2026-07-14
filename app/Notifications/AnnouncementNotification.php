<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;

/**
 * An admin-written message to a segment of users (M23 push composer).
 *
 * It is an ordinary notification, so it rides every channel the recipient has
 * left switched on — in-app and live for everyone, push/email/SMS where the
 * install is configured for them. No second delivery path.
 */
class AnnouncementNotification extends PlatformNotification
{
    public function __construct(
        public readonly string $heading,
        public readonly string $message,
        public readonly string $link = '',
    ) {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::Announcement;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'title' => $this->heading,
            'body' => $this->message,
            'url' => $this->link,
        ];
    }
}
