<?php

namespace App\Domain\Comms\Actions;

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\NotificationPreferences;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes a slice of the event × channel matrix (M23).
 *
 * `$user = null` writes the platform defaults an admin owns; a user writes
 * their own opt-outs, which win over those defaults. One action for both
 * because the row shape is identical — the nullable user *is* the distinction
 * the table was designed around.
 */
class SaveNotificationPreferences
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    /**
     * @param  list<array{event: string, channel: string, enabled: bool}>  $rows
     */
    public function handle(?User $user, array $rows): void
    {
        DB::transaction(function () use ($user, $rows): void {
            foreach ($rows as $row) {
                $event = NotificationEvent::tryFrom($row['event']);
                $channel = NotificationChannel::tryFrom($row['channel']);

                // A payload naming an event or channel we do not have is
                // ignored, never stored: the matrix is the enum, not the form.
                if ($event === null || $channel === null) {
                    continue;
                }

                $existing = NotificationPreference::query()
                    ->when($user === null,
                        fn ($query) => $query->whereNull('user_id'),
                        fn ($query) => $query->where('user_id', $user?->id),
                    )
                    ->where('event_key', $event->value)
                    ->where('channel', $channel->value)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof NotificationPreference) {
                    $existing->update(['is_enabled' => $row['enabled']]);

                    continue;
                }

                NotificationPreference::query()->create([
                    'user_id' => $user?->id,
                    'event_key' => $event->value,
                    'channel' => $channel->value,
                    'is_enabled' => $row['enabled'],
                ]);
            }
        });

        $this->preferences->flush();
    }
}
