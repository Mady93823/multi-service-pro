<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\DashboardMetrics;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics): Response
    {
        return Inertia::render('admin/dashboard', [
            'tiles' => $metrics->tiles(),
            'bookings_per_day' => $metrics->bookingsPerDay(),
            'revenue_per_day' => $metrics->revenuePerDay(),
            'top_services' => $metrics->topServices(),
            'leaderboard' => $metrics->providerLeaderboard(),
        ]);
    }
}
