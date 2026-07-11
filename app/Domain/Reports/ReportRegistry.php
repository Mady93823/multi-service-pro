<?php

namespace App\Domain\Reports;

use App\Domain\Reports\Reports\BookingsReport;
use App\Domain\Reports\Reports\EarningsReport;
use App\Domain\Reports\Reports\ProvidersReport;
use App\Domain\Reports\Reports\ServicesReport;

/**
 * Slug → report resolver. The route model-binds `{report}` through here so an
 * unknown slug is a 404, and the queued export job can rebuild its report
 * from the slug alone.
 */
class ReportRegistry
{
    /**
     * @var array<string, class-string<Report>>
     */
    private const REPORTS = [
        'bookings' => BookingsReport::class,
        'earnings' => EarningsReport::class,
        'services' => ServicesReport::class,
        'providers' => ProvidersReport::class,
    ];

    public function find(string $slug): ?Report
    {
        $class = self::REPORTS[$slug] ?? null;

        return $class === null ? null : app($class);
    }

    public function findOrFail(string $slug): Report
    {
        $report = $this->find($slug);

        abort_if($report === null, 404);

        return $report;
    }

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class): Report => app($class), array_values(self::REPORTS));
    }
}
