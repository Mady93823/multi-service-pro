<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Models\Earning;
use Illuminate\Database\Eloquent\Builder;

/**
 * The earnings ledger row by row — commission the platform charged, what each
 * provider keeps, reversals included as their own negative rows.
 *
 * @phpstan-import-type ReportRow from Report
 * @phpstan-import-type PaginatedRows from Report
 */
class EarningsReport implements Report
{
    public function slug(): string
    {
        return 'earnings';
    }

    public function title(): string
    {
        return __('Earnings report');
    }

    public function columns(): array
    {
        return [
            'date' => __('Date'),
            'booking' => __('Booking'),
            'provider' => __('Provider'),
            'type' => __('Type'),
            'gross' => __('Gross'),
            'commission' => __('Commission'),
            'collected' => __('Collected by provider'),
            'net' => __('Net'),
            'status' => __('Status'),
        ];
    }

    public function statusOptions(): array
    {
        return array_column(EarningStatus::cases(), 'value');
    }

    /**
     * @return PaginatedRows
     */
    public function paginate(ReportFilters $filters, int $perPage = 25): array
    {
        $page = $this->query($filters)->paginate($perPage)->withQueryString();

        return [
            'data' => array_map(fn (Earning $earning): array => $this->row($earning), $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'prev_page_url' => $page->previousPageUrl(),
            'next_page_url' => $page->nextPageUrl(),
        ];
    }

    public function rows(ReportFilters $filters): \Generator
    {
        foreach ($this->query($filters)->lazy(500) as $earning) {
            yield $this->row($earning);
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    /**
     * @return Builder<Earning>
     */
    private function query(ReportFilters $filters): Builder
    {
        return Earning::query()
            ->with(['provider:id,name', 'booking:id,code'])
            ->when($filters->from, fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters->status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->orderByDesc('created_at');
    }

    /**
     * @return ReportRow
     */
    private function row(Earning $earning): array
    {
        return [
            'date' => $earning->created_at?->format('Y-m-d H:i'),
            'booking' => $earning->booking?->code,
            'provider' => $earning->provider?->name,
            'type' => $earning->type->value,
            'gross' => (float) $earning->gross,
            'commission' => (float) $earning->commission,
            'collected' => (float) $earning->collected_amount,
            'net' => (float) $earning->net,
            'status' => $earning->status->value,
        ];
    }
}
