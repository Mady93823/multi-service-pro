<?php

namespace App\Domain\Comms\Actions;

use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends an admin's message to a segment (M23 push composer).
 *
 * It reuses the ordinary notification pipeline — same channels, same
 * preferences, same in-app feed — so an announcement is not a special kind of
 * message with its own delivery bugs. Blocked users are skipped: an account
 * that cannot log in has no business receiving marketing.
 *
 * Sent in chunks because a segment is unbounded: `Notification::send()` queues
 * one job per recipient (the notification is ShouldQueue), and holding 50k
 * users in memory to do it would be the one place this falls over.
 */
class SendAnnouncement
{
    public const SEGMENTS = ['all', 'customers', 'providers'];

    public function handle(string $segment, string $heading, string $message, string $link = ''): int
    {
        $sent = 0;

        $this->recipients($segment)->chunkById(500, function (Collection $users) use (&$sent, $heading, $message, $link): void {
            Notification::send($users, new AnnouncementNotification($heading, $message, $link));

            $sent += $users->count();
        });

        return $sent;
    }

    /**
     * @return Builder<User>
     */
    private function recipients(string $segment): Builder
    {
        $query = User::query()->where('is_active', true);

        return match ($segment) {
            'customers' => $query->role('customer'),
            'providers' => $query->role('provider'),
            default => $query->role(['customer', 'provider']),
        };
    }
}
