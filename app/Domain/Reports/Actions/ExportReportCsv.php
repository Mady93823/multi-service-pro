<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Jobs\GenerateReportExport;
use App\Models\User;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export with the M06 sync-queue guard: small result sets (or installs
 * running the sync queue driver) stream the file inline; big ranges on a real
 * queue are generated in the background and delivered via notification.
 */
class ExportReportCsv
{
    /**
     * Above this many rows the export is queued instead of streamed.
     */
    public const SYNC_ROW_LIMIT = 2000;

    /**
     * Returns a streamed download, or null when the export was queued.
     */
    public function handle(User $admin, Report $report, ReportFilters $filters): ?StreamedResponse
    {
        $filename = sprintf('%s-%s-%s.csv', $report->slug(), now()->format('Ymd-His'), Str::lower(Str::random(6)));

        $queueIsSync = config('queue.default') === 'sync';

        if (! $queueIsSync && $report->count($filters) > self::SYNC_ROW_LIMIT) {
            GenerateReportExport::dispatch($admin->id, $report->slug(), $filters->toArray(), $filename);

            return null;
        }

        return response()->streamDownload(function () use ($report, $filters): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // UTF-8 BOM so Excel renders ₹ and Indian names correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $writer = Writer::createFromStream($out);
            $writer->insertOne(array_values($report->columns()));

            foreach ($report->rows($filters) as $row) {
                $writer->insertOne(array_values($row));
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
