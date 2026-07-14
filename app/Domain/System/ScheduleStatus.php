<?php

namespace App\Domain\System;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * Is the scheduler actually running? (M24)
 *
 * A cron line nobody added is the single most common broken install of a
 * self-hosted PHP product: payouts never release, unpaid bookings never expire,
 * exports pile up — and nothing errors. Nothing errors is exactly the problem,
 * so `system:heartbeat` stamps a settings key every five minutes and this turns
 * a stale stamp into the loudest banner in the panel.
 */
class ScheduleStatus
{
    public const LAST_RUN_KEY = 'system.scheduler_last_run';

    /**
     * Stamped by the queued `QueueHeartbeat` job, not by the command that
     * dispatches it — so a fresh stamp means a **worker** picked the job up,
     * which is the only claim worth making (P7.3).
     */
    public const QUEUE_LAST_RUN_KEY = 'system.queue_last_run';

    /** A scheduler that has not run in this long is not running. */
    private const STALE_AFTER_MINUTES = 60;

    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly Schedule $schedule,
    ) {}

    public function lastRun(): ?Carbon
    {
        $stamp = $this->settings->string(self::LAST_RUN_KEY);

        if ($stamp === '') {
            return null;
        }

        return Carbon::parse($stamp);
    }

    /** True when the scheduler has never run, or has not run recently. */
    public function isStale(): bool
    {
        $lastRun = $this->lastRun();

        return $lastRun === null || $lastRun->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function queueLastRun(): ?Carbon
    {
        $stamp = $this->settings->string(self::QUEUE_LAST_RUN_KEY);

        return $stamp === '' ? null : Carbon::parse($stamp);
    }

    /**
     * A worker that has not picked up a heartbeat is not running — but say so
     * only once the *scheduler* is alive, because the heartbeat is dispatched by
     * cron: with no cron there is nothing for a worker to pick up, and blaming
     * the worker for that would send the operator after the wrong process.
     */
    public function queueIsStale(): bool
    {
        if ($this->isStale()) {
            return false;
        }

        $lastRun = $this->queueLastRun();

        return $lastRun === null || $lastRun->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * @return list<array{command: string, expression: string, next_run: ?string}>
     */
    public function tasks(): array
    {
        return array_values(array_map(fn (Event $event): array => [
            'command' => $this->name($event),
            'expression' => $event->expression,
            'next_run' => $this->nextRun($event),
        ], $this->schedule->events()));
    }

    /** The exact line an operator has to paste into crontab. */
    public function cronLine(): string
    {
        return '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1';
    }

    private function name(Event $event): string
    {
        if ($event->description !== null && $event->description !== '') {
            return $event->description;
        }

        // A closure task has no command line; the summary is the best we have.
        return $event->getSummaryForDisplay();
    }

    private function nextRun(Event $event): ?string
    {
        try {
            return Carbon::instance($event->nextRunDate())->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
