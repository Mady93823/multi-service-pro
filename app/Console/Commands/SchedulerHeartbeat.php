<?php

namespace App\Console\Commands;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\System\ScheduleStatus;
use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Command;

/**
 * Stamps "the scheduler ran" (M24).
 *
 * The key is deliberately NOT in `SettingsRegistry::defaults()` — it is machine
 * state, not a setting an admin edits, and every default key must be owned by
 * an editable group (D24's coverage test). `set()` writes it happily either way.
 */
class SchedulerHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record that the task scheduler is alive.';

    public function handle(SettingsRegistry $settings): int
    {
        $settings->set(ScheduleStatus::LAST_RUN_KEY, now()->toIso8601String());

        // And ask a *worker* to stamp its own clock (P7.3). The scheduler cannot
        // vouch for the queue: a missing worker is the quieter broken install —
        // every page loads and the notifications simply never leave the table.
        QueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
