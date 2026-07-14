<?php

namespace App\Domain\Comms\Enums;

/**
 * The channels a preference can switch (M23).
 *
 * `database` and `broadcast` are not here on purpose: they are always on. The
 * in-app feed is the platform's own record of what it did — a user who turned
 * it off would have no way to see that a booking was cancelled or a refund
 * paid, and the bell would silently stop working.
 */
enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';
    case Fcm = 'fcm';

    public function label(): string
    {
        return match ($this) {
            self::Mail => __('Email'),
            self::Sms => __('SMS'),
            self::Fcm => __('Push'),
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
