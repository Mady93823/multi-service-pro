<?php

namespace App\Console\Commands;

use App\Domain\Settings\SettingsRegistry;
use App\Models\TrackingPoint;
use Illuminate\Console\Command;

/**
 * Trail history is only useful live; prune points past the retention window
 * (05-Live-Tracking — ~1 row/3s per journey adds up). Ended sessions stay for
 * the booking record; their points go.
 */
class PruneTrackingPoints extends Command
{
    protected $signature = 'tracking:prune';

    protected $description = 'Delete tracking points older than the retention window';

    public function handle(SettingsRegistry $settings): int
    {
        $days = $settings->integer('tracking.points_retention_days', 30);
        $cutoff = now()->subDays($days);

        $deleted = TrackingPoint::query()->where('recorded_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} tracking point(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
