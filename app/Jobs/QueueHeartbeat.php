<?php

namespace App\Jobs;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\System\ScheduleStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proof that a queue worker exists (P7.3).
 *
 * A missing worker is the second-most-common broken install after a missing
 * cron, and it is *quieter*: every page loads, every booking is placed, and the
 * notifications simply pile up in the `jobs` table unsent. Nobody notices until
 * a customer says they never got the confirmation.
 *
 * `system:heartbeat` (cron, every five minutes) dispatches this; the worker that
 * runs it stamps the clock. So a fresh stamp means "a worker picked this up",
 * which is the only claim worth making. On a `sync` queue it stamps immediately
 * — also honest: there, the request *is* the worker.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(SettingsRegistry $settings): void
    {
        $settings->set(ScheduleStatus::QUEUE_LAST_RUN_KEY, now()->toIso8601String());
    }
}
