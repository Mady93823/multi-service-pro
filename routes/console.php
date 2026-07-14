<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M24: proof of life. A scheduler nobody wired up is the most common broken
// install — the admin System screen reads this stamp and shouts when it is stale.
Schedule::command('system:heartbeat')->everyFiveMinutes();

// M07: keep tracking_points from growing without bound.
Schedule::command('tracking:prune')->dailyAt('03:30');

// M08: free slots held by bookings whose payment window has closed.
Schedule::command('bookings:expire-unpaid')->everyFiveMinutes();

// M09: end the hold window on completed jobs so providers can cash out.
Schedule::command('earnings:release')->dailyAt('04:00');

// M13: CSV exports are one-shot downloads — clear anything older than 7 days.
Schedule::call(function (): void {
    $dir = storage_path('app'.DIRECTORY_SEPARATOR.'exports');

    foreach (glob($dir.DIRECTORY_SEPARATOR.'*.csv') ?: [] as $file) {
        if (filemtime($file) !== false && filemtime($file) < now()->subDays(7)->getTimestamp()) {
            @unlink($file);
        }
    }
})->name('exports-prune')->dailyAt('03:45');
