<?php

namespace App\Domain\Reports;

/**
 * A filterable admin report (M13). Implementations expose mapped rows only —
 * never a query builder — so the screen, the inline CSV stream and the queued
 * CSV job all read the exact same figures.
 *
 * @phpstan-type ReportRow array<string, string|int|float|null>
 * @phpstan-type PaginatedRows array{data: list<ReportRow>, current_page: int, last_page: int, prev_page_url: string|null, next_page_url: string|null}
 */
interface Report
{
    public function slug(): string;

    public function title(): string;

    /**
     * Column key => translated label, in render order.
     *
     * @return array<string, string>
     */
    public function columns(): array;

    /**
     * Options for the status filter select; empty when the report has none.
     *
     * @return list<string>
     */
    public function statusOptions(): array;

    /**
     * One page of mapped rows in the exact `NativePaginated<T>` shape the TS
     * side expects (landmine 19) — a plain array rather than a paginator, so
     * the wire format is pinned here instead of by serializer behavior.
     *
     * @return PaginatedRows
     */
    public function paginate(ReportFilters $filters, int $perPage = 25): array;

    /**
     * Streams every matching row for CSV export.
     *
     * @return \Generator<int, ReportRow>
     */
    public function rows(ReportFilters $filters): \Generator;

    public function count(ReportFilters $filters): int;
}
