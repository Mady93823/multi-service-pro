<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M07: keep tracking_points from growing without bound.
Schedule::command('tracking:prune')->dailyAt('03:30');

// M08: free slots held by bookings whose payment window has closed.
Schedule::command('bookings:expire-unpaid')->everyFiveMinutes();
