<?php

namespace App\Console\Commands;

use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Console\Command;

/**
 * Housekeeping for the media library (M18).
 *
 * Deliberately **not scheduled**: this deletes files, and an admin who uploaded
 * an asset they have not used yet should not lose it while they sleep. It is a
 * tool an operator runs, prints what it would do, and then confirms with --force.
 */
class PruneMediaLibrary extends Command
{
    protected $signature = 'media:prune-library {--days=30 : Only assets older than this} {--force : Actually delete}';

    protected $description = 'List (or delete, with --force) library files that nothing uses';

    public function handle(DeleteMediaAsset $delete): int
    {
        $days = max(0, (int) $this->option('days'));
        $force = (bool) $this->option('force');

        $candidates = MediaAsset::query()
            ->where('created_at', '<=', now()->subDays($days))
            ->get()
            ->filter(fn (MediaAsset $asset): bool => $asset->usageCount() === 0);

        if ($candidates->isEmpty()) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        foreach ($candidates as $asset) {
            $this->line(($force ? 'Deleting' : 'Would delete').": #{$asset->id} {$asset->name}");

            if ($force) {
                $delete->handle($asset);
            }
        }

        $this->info(sprintf(
            '%d unused file(s)%s.',
            $candidates->count(),
            $force ? ' deleted' : ' — re-run with --force to delete them',
        ));

        return self::SUCCESS;
    }
}
