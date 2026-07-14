<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * The update path (M24, finishing what M15's installer started).
 *
 * A buyer who uploads a new release has to run migrations and clear caches, and
 * "did you remember to run artisan?" is a support ticket. This is that sequence,
 * in one command the admin System screen can also press.
 *
 * `storage:link` is best-effort: a host that forbids symlinks must still finish
 * the update (M18's lesson) rather than failing halfway.
 */
class AppUpdate extends Command
{
    protected $signature = 'app:update';

    protected $description = 'Run migrations and refresh caches after uploading a new release.';

    public function handle(): int
    {
        $this->components->info('Running migrations…');
        $this->call('migrate', ['--force' => true]);

        $this->components->info('Clearing caches…');
        $this->call('optimize:clear');

        $this->components->info('Linking public storage…');
        $this->callSilently('storage:link');

        $this->components->info('Update complete.');

        return self::SUCCESS;
    }
}
