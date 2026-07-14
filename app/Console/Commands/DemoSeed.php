<?php

namespace App\Console\Commands;

use Database\Seeders\ShowcaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * `php artisan demo:seed` — rebuild the database as a business that has been
 * trading for three months.
 *
 * This exists as a command rather than as a seeder you remember to chain because
 * it has to switch three things off first, and forgetting any one of them turns a
 * demo into a stack trace:
 *
 * - **Broadcasting.** A hundred and forty bookings move through the state
 *   machine, and every transition broadcasts. With `BROADCAST_CONNECTION=reverb`
 *   and no Reverb process running, the seeder dies on the first booking.
 * - **The queue.** Notifications are `ShouldQueue`; on a `database` queue they
 *   would sit in the table and the demo's notification bell would be empty.
 *   Forced to `sync` so the bell is full when the client looks at it.
 * - **Mail.** If the operator has configured SMTP, seeding would send a hundred
 *   real emails to `@demo.test`, which is a bounce storm, not a demo.
 */
class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--fresh : Drop every table first (this deletes all data)}';

    protected $description = 'Seed the showcase demo: photographs, 90 days of bookings, providers, reviews and payouts.';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('demo:seed refuses to run in production. This is a demo dataset, not a business.');

            return self::FAILURE;
        }

        Config::set([
            'broadcasting.default' => 'null',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        if ($this->option('fresh')) {
            if (! $this->confirm('This drops every table and rebuilds the database. Continue?', true)) {
                return self::FAILURE;
            }

            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => ShowcaseSeeder::class, '--force' => true]);

        $this->newLine();
        $this->components->info('Showcase ready.');
        $this->components->twoColumnDetail('Admin', 'admin@demo.test / password');
        $this->components->twoColumnDetail('Customer', 'customer@demo.test / password');
        $this->components->twoColumnDetail('Provider', 'provider@demo.test / password');
        $this->newLine();
        $this->line('  Every demo account uses the password <options=bold>password</>.');
        $this->line('  Start here: <options=bold>/</> then <options=bold>/admin/dashboard</>.');

        return self::SUCCESS;
    }
}
