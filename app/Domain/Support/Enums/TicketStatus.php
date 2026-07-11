<?php

namespace App\Domain\Support\Enums;

/**
 * open      — waiting on support
 * pending   — support replied, waiting on the user (a user reply reopens)
 * resolved  — marked solved; a user reply reopens it
 * closed    — final and read-only (gate criterion), only admins close
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Pending => __('Awaiting your reply'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }
}
