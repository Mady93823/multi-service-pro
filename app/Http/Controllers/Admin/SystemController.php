<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\System\Actions\RunUpdate;
use App\Domain\System\ScheduleStatus;
use App\Domain\System\SystemHealth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System status, cron health and the update runner (M24).
 */
class SystemController extends Controller
{
    public function index(SystemHealth $health, ScheduleStatus $schedule): Response
    {
        return Inertia::render('admin/system/index', [
            'about' => $health->about(),
            'checks' => $health->checks(),
            'scheduler' => [
                'last_run' => $schedule->lastRun()?->toIso8601String(),
                'is_stale' => $schedule->isStale(),
                'cron_line' => $schedule->cronLine(),
                'tasks' => $schedule->tasks(),
            ],
        ]);
    }

    /**
     * The same `app:update` an operator with SSH would run — one update path,
     * not two. The output comes back so a failed migration is read here.
     */
    public function update(Request $request, RunUpdate $action, ActivityLogger $activity): RedirectResponse
    {
        $output = $action->handle();

        $activity->log($request->user(), 'system.update', null, []);

        return back()->with('success', __('Update finished.'))->with('update_output', $output);
    }
}
