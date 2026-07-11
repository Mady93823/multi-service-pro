<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->string('action')->toString();

        $logs = ActivityLog::query()
            ->with('actor:id,name')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            // Raw ->through() paginator — NativePaginated<T> on the TS side.
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'actor' => $log->actor?->name,
                'action' => $log->action,
                'subject_type' => $log->subject_type !== null ? class_basename($log->subject_type) : null,
                'subject_id' => $log->subject_id,
                'context' => $log->context,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('admin/activity/index', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => ['action' => $action],
        ]);
    }
}
