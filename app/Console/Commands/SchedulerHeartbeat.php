<?php

namespace App\Console\Commands;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\System\ScheduleStatus;
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

        return self::SUCCESS;
    }
}
