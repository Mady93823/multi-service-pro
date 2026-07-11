<?php

namespace Tests\Support;

use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;

/**
 * Report stub whose count() is over the sync export limit — lets tests hit
 * ExportReportCsv's queued branch without seeding thousands of rows.
 */
class FakeHugeReport implements Report
{
    public function __construct(private readonly int $count = 100000) {}

    public function slug(): string
    {
        return 'bookings';
    }

    public function title(): string
    {
        return 'Fake huge report';
    }

    public function columns(): array
    {
        return ['code' => 'Code'];
    }

    public function statusOptions(): array
    {
        return [];
    }

    public function paginate(ReportFilters $filters, int $perPage = 25): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'prev_page_url' => null,
            'next_page_url' => null,
        ];
    }

    public function rows(ReportFilters $filters): \Generator
    {
        yield from [];
    }

    public function count(ReportFilters $filters): int
    {
        return $this->count;
    }
}
