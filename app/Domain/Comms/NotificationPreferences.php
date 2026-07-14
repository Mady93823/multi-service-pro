<?php

namespace App\Domain\Comms;

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Who gets what, on which channel (M23).
 *
 * Three layers, most specific first: the user's own opt-out, then the platform
 * default an admin set on the matrix, then the shipped default in
 * NotificationEvent::defaults(). A channel a user switched off stays off even
 * if the admin later switches the default on — an opt-out is a promise.
 */
class NotificationPreferences
{
    /** @var array<string, bool>|null */
    private ?array $platform = null;

    public function enabled(NotificationEvent $event, NotificationChannel $channel, ?User $user = null): bool
    {
        if ($user !== null) {
            $own = $this->userValue($user, $event, $channel);

            if ($own !== null) {
                return $own;
            }
        }

        return $this->platformValue($event, $channel);
    }

    /**
     * The platform matrix as the admin screen wants it: event => channel => bool.
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach (NotificationEvent::all() as $event) {
            foreach (NotificationChannel::all() as $channel) {
                $matrix[$event->value][$channel->value] = $this->platformValue($event, $channel);
            }
        }

        return $matrix;
    }

    /**
     * A user's effective switches — the platform matrix with their opt-outs on top.
     *
     * @return array<string, array<string, bool>>
     */
    public function forUser(User $user): array
    {
        $matrix = [];

        foreach (NotificationEvent::all() as $event) {
            foreach (NotificationChannel::all() as $channel) {
                $matrix[$event->value][$channel->value] = $this->enabled($event, $channel, $user);
            }
        }

        return $matrix;
    }

    public function flush(): void
    {
        $this->platform = null;
    }

    private function platformValue(NotificationEvent $event, NotificationChannel $channel): bool
    {
        $this->platform ??= NotificationPreference::query()
            ->platform()
            ->get()
            ->mapWithKeys(fn (NotificationPreference $row): array => [
                $row->event_key.'|'.$row->channel => $row->is_enabled,
            ])
            ->all();

        return $this->platform[$event->value.'|'.$channel->value]
            ?? ($event->defaults()[$channel->value] ?? false);
    }

    private function userValue(User $user, NotificationEvent $event, NotificationChannel $channel): ?bool
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_key', $event->value)
            ->where('channel', $channel->value)
            ->first();

        return $row?->is_enabled;
    }
}
