<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Services ranked by revenue on completed bookings. Aggregates item price
 * snapshots (× qty); addons are excluded — they are priced per booking line,
 * not per service. Date range applies to `bookings.completed_at`.
 *
 * @phpstan-import-type ReportRow from Report
 * @phpstan-import-type PaginatedRows from Report
 */
class ServicesReport implements Report
{
    public function slug(): string
    {
        return 'services';
    }

    public function title(): string
    {
        return __('Top services report');
    }

    public function columns(): array
    {
        return [
            'service' => __('Service'),
            'bookings' => __('Bookings'),
            'qty' => __('Quantity'),
            'revenue' => __('Revenue'),
        ];
    }

    public function statusOptions(): array
    {
        return [];
    }

    /**
     * @return PaginatedRows
     */
    public function paginate(ReportFilters $filters, int $perPage = 25): array
    {
        $page = $this->query($filters)->paginate($perPage)->withQueryString();

        return [
            'data' => array_map(fn (object $row): array => $this->row($row), $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'prev_page_url' => $page->previousPageUrl(),
            'next_page_url' => $page->nextPageUrl(),
        ];
    }

    public function rows(ReportFilters $filters): \Generator
    {
        foreach ($this->query($filters)->lazy(500) as $row) {
            yield $this->row($row);
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    /**
     * @return Builder<BookingItem>
     */
    private function query(ReportFilters $filters): Builder
    {
        return BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.status', BookingStatus::Completed->value)
            ->when($filters->from, fn (Builder $q, $from) => $q->where('bookings.completed_at', '>=', $from))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('bookings.completed_at', '<=', $to))
            ->groupBy('booking_items.name_snapshot')
            ->orderByDesc(DB::raw('sum(booking_items.price_snapshot * booking_items.qty)'))
            ->select(DB::raw(
                'booking_items.name_snapshot,'
                .' count(distinct booking_items.booking_id) as bookings_count,'
                .' sum(booking_items.qty) as qty,'
                .' sum(booking_items.price_snapshot * booking_items.qty) as revenue'
            ));
    }

    /**
     * @return ReportRow
     */
    private function row(object $row): array
    {
        return [
            'service' => (string) $row->name_snapshot,
            'bookings' => (int) $row->bookings_count,
            'qty' => (int) $row->qty,
            'revenue' => round((float) $row->revenue, 2),
        ];
    }
}
