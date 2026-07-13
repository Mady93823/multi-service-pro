<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;

/**
 * Newsletter list (M19). It exists as a Report so the CSV export the admin asks
 * for is the one M13 already built — inline stream under 2000 rows, queued job
 * above it — rather than a second export path that drifts.
 *
 * @phpstan-import-type ReportRow from Report
 * @phpstan-import-type PaginatedRows from Report
 */
class SubscribersReport implements Report
{
    public function slug(): string
    {
        return 'subscribers';
    }

    public function title(): string
    {
        return __('Newsletter subscribers');
    }

    public function columns(): array
    {
        return [
            'email' => __('Email'),
            'status' => __('Status'),
            'source' => __('Source'),
            'joined' => __('Joined'),
        ];
    }

    public function statusOptions(): array
    {
        return ['subscribed', 'unsubscribed'];
    }

    /**
     * @return PaginatedRows
     */
    public function paginate(ReportFilters $filters, int $perPage = 25): array
    {
        $page = $this->query($filters)->paginate($perPage)->withQueryString();

        return [
            'data' => array_map(fn (Subscriber $row): array => $this->row($row), $page->items()),
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
     * @return Builder<Subscriber>
     */
    private function query(ReportFilters $filters): Builder
    {
        return Subscriber::query()
            ->when($filters->from, fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters->status === 'subscribed', fn (Builder $q) => $q->whereNull('unsubscribed_at'))
            ->when($filters->status === 'unsubscribed', fn (Builder $q) => $q->whereNotNull('unsubscribed_at'))
            ->latest('id');
    }

    /**
     * @return ReportRow
     */
    private function row(Subscriber $subscriber): array
    {
        return [
            'email' => $subscriber->email,
            'status' => $subscriber->unsubscribed_at === null ? __('Subscribed') : __('Unsubscribed'),
            'source' => (string) $subscriber->source,
            'joined' => $subscriber->created_at?->toDateString(),
        ];
    }
}
