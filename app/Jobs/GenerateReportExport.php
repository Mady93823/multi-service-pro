<?php

namespace App\Jobs;

use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportRegistry;
use App\Models\User;
use App\Notifications\ReportExportReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use League\Csv\Writer;

/**
 * Background leg of the CSV export (M13) — big ranges only, dispatched by
 * ExportReportCsv when a real queue driver is running. Writes to
 * storage/app/exports and notifies the requesting admin with a download link.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    /**
     * @param  array{from: string|null, to: string|null, status: string|null}  $filters
     */
    public function __construct(
        public readonly int $adminId,
        public readonly string $reportSlug,
        public readonly array $filters,
        public readonly string $filename,
    ) {}

    public function handle(ReportRegistry $registry): void
    {
        $admin = User::query()->find($this->adminId);

        if ($admin === null) {
            return;
        }

        $report = $registry->find($this->reportSlug);

        if ($report === null) {
            return;
        }

        $dir = storage_path('app/exports');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.$this->filename;
        $filters = ReportFilters::fromArray($this->filters);

        $writer = Writer::createFromPath($path, 'w+');
        $writer->setOutputBOM(Writer::BOM_UTF8);
        $writer->insertOne(array_values($report->columns()));

        foreach ($report->rows($filters) as $row) {
            $writer->insertOne(array_values($row));
        }

        $admin->notify(new ReportExportReady($report->title(), $this->filename));
    }
}
