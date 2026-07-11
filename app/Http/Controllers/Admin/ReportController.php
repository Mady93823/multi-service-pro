<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reports\Actions\ExportReportCsv;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function show(ReportFilterRequest $request, string $report): Response
    {
        $instance = $this->registry->findOrFail($report);

        /** @var array{from?: string|null, to?: string|null, status?: string|null} $validated */
        $validated = $request->validated();
        $filters = ReportFilters::fromArray($validated);

        $columns = [];
        foreach ($instance->columns() as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label];
        }

        return Inertia::render('admin/reports/show', [
            'report' => [
                'slug' => $instance->slug(),
                'title' => $instance->title(),
                'columns' => $columns,
                'status_options' => $instance->statusOptions(),
            ],
            'filters' => $filters->toArray(),
            // Raw ->through() paginator — NativePaginated<T> on the TS side.
            'rows' => $instance->paginate($filters),
            'sync_limit' => ExportReportCsv::SYNC_ROW_LIMIT,
        ]);
    }

    public function export(
        ReportFilterRequest $request,
        string $report,
        ExportReportCsv $export,
    ): StreamedResponse|RedirectResponse {
        $instance = $this->registry->findOrFail($report);

        /** @var array{from?: string|null, to?: string|null, status?: string|null} $validated */
        $validated = $request->validated();
        $filters = ReportFilters::fromArray($validated);

        /** @var User $admin */
        $admin = $request->user();

        $response = $export->handle($admin, $instance, $filters);

        if ($response === null) {
            return back()->with('success', __("Export queued — you'll get a notification when it is ready."));
        }

        return $response;
    }
}
