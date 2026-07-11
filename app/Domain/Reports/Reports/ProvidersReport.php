<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportFilters;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Provider leaderboard: M10's recomputed job/rating counters plus lifetime
 * money summed from earnings rows. The date range narrows the earnings sums
 * (on `earnings.created_at`), not the provider list; the status filter is the
 * provider's approval status.
 *
 * @phpstan-import-type ReportRow from Report
 * @phpstan-import-type PaginatedRows from Report
 */
class ProvidersReport implements Report
{
    public function slug(): string
    {
        return 'providers';
    }

    public function title(): string
    {
        return __('Provider leaderboard');
    }

    public function columns(): array
    {
        return [
            'name' => __('Provider'),
            'status' => __('Status'),
            'jobs_completed' => __('Jobs completed'),
            'rating_avg' => __('Rating'),
            'rating_count' => __('Ratings'),
            'gross' => __('Gross'),
            'commission' => __('Commission'),
            'net' => __('Net'),
        ];
    }

    public function statusOptions(): array
    {
        return array_column(ProviderApprovalStatus::cases(), 'value');
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
     * @return Builder<ProviderProfile>
     */
    private function query(ReportFilters $filters): Builder
    {
        return ProviderProfile::query()
            ->join('users', 'users.id', '=', 'provider_profiles.user_id')
            ->leftJoin('earnings', function ($join) use ($filters): void {
                $join->on('earnings.provider_id', '=', 'provider_profiles.user_id');

                if ($filters->from !== null) {
                    $join->where('earnings.created_at', '>=', $filters->from);
                }
                if ($filters->to !== null) {
                    $join->where('earnings.created_at', '<=', $filters->to);
                }
            })
            ->when($filters->status, fn (Builder $q, string $status) => $q->where('provider_profiles.approval_status', $status))
            ->groupBy('provider_profiles.user_id', 'users.name', 'provider_profiles.approval_status', 'provider_profiles.jobs_completed', 'provider_profiles.rating_avg', 'provider_profiles.rating_count')
            ->orderByDesc('provider_profiles.jobs_completed')
            ->orderByDesc('provider_profiles.rating_avg')
            ->select(DB::raw(
                'provider_profiles.user_id, users.name, provider_profiles.approval_status as status,'
                .' provider_profiles.jobs_completed, provider_profiles.rating_avg, provider_profiles.rating_count,'
                .' coalesce(sum(earnings.gross), 0) as gross,'
                .' coalesce(sum(earnings.commission), 0) as commission,'
                .' coalesce(sum(earnings.net), 0) as net'
            ));
    }

    /**
     * @return ReportRow
     */
    private function row(object $row): array
    {
        return [
            'name' => (string) $row->name,
            'status' => (string) $row->status,
            'jobs_completed' => (int) $row->jobs_completed,
            'rating_avg' => (float) $row->rating_avg,
            'rating_count' => (int) $row->rating_count,
            'gross' => round((float) $row->gross, 2),
            'commission' => round((float) $row->commission, 2),
            'net' => round((float) $row->net, 2),
        ];
    }
}
