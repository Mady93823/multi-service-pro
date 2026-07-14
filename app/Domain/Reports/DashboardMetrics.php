<?php

namespace App\Domain\Reports;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\City;
use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\ProviderProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model behind the admin dashboard (M13). Every figure is a straight
 * aggregate over snapshot columns (`bookings.total`, `earnings.*`) — nothing
 * is recomputed from current prices, so the numbers reconcile with raw SQL
 * at any point in time.
 */
class DashboardMetrics
{
    /**
     * Statuses that mean a provider currently owns the job.
     */
    private const OPEN_JOB_STATUSES = [
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
    ];

    /**
     * @return array{
     *     bookings_today: int,
     *     bookings_week: int,
     *     gmv_month: float,
     *     commission_month: float,
     *     open_jobs: int,
     *     pending_payouts_count: int,
     *     pending_payouts_amount: float,
     *     providers_online: int,
     *     providers_pending_kyc: int,
     * }
     */
    public function tiles(): array
    {
        return [
            'bookings_today' => Booking::query()->whereDate('created_at', Carbon::today())->count(),
            'bookings_week' => Booking::query()->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'gmv_month' => round((float) Booking::query()
                ->where('status', BookingStatus::Completed->value)
                ->where('completed_at', '>=', Carbon::now()->startOfMonth())
                ->sum('total'), 2),
            'commission_month' => round((float) Earning::query()
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->sum('commission'), 2),
            'open_jobs' => Booking::query()
                ->whereIn('status', array_column(self::OPEN_JOB_STATUSES, 'value'))
                ->count(),
            'pending_payouts_count' => PayoutRequest::query()
                ->where('status', PayoutStatus::Requested->value)
                ->count(),
            'pending_payouts_amount' => round((float) PayoutRequest::query()
                ->where('status', PayoutStatus::Requested->value)
                ->sum('amount'), 2),
            'providers_online' => ProviderProfile::query()
                ->where('approval_status', ProviderApprovalStatus::Approved->value)
                ->where('is_online', true)
                ->count(),
            'providers_pending_kyc' => ProviderProfile::query()
                ->where('approval_status', ProviderApprovalStatus::Pending->value)
                ->count(),
        ];
    }

    /**
     * Bookings placed per day, zero-filled for the whole window.
     *
     * @return list<array{date: string, bookings: int}>
     */
    public function bookingsPerDay(int $days = 30): array
    {
        $from = Carbon::today()->subDays($days - 1);

        /** @var array<string, int> $counts */
        $counts = Booking::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day')
            ->all();

        return $this->fillDays($from, $days, fn (string $day): array => [
            'bookings' => (int) ($counts[$day] ?? 0),
        ]);
    }

    /**
     * Gross (GMV) and commission per day from the earnings ledger. Reversal
     * rows carry negative amounts, so a refunded job nets out on the day of
     * the refund — the chart never lies about money that went back.
     *
     * @return list<array{date: string, gross: float, commission: float}>
     */
    public function revenuePerDay(int $days = 30): array
    {
        $from = Carbon::today()->subDays($days - 1);

        /** @var array<string, object{day: string, gross: string, commission: string}> $sums */
        $sums = Earning::query()
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->select(DB::raw('date(created_at) as day, sum(gross) as gross, sum(commission) as commission'))
            ->get()
            ->keyBy('day')
            ->all();

        return $this->fillDays($from, $days, fn (string $day): array => [
            'gross' => round((float) ($sums[$day]->gross ?? 0), 2),
            'commission' => round((float) ($sums[$day]->commission ?? 0), 2),
        ]);
    }

    /**
     * Top services by item revenue (price snapshots × qty) on completed
     * bookings. Addons are deliberately excluded — they are priced per
     * booking line in `addons_snapshot`, not per service.
     *
     * @return list<array{service: string, bookings: int, revenue: float}>
     */
    public function topServices(int $days = 30, int $limit = 8): array
    {
        /** @var list<object{name_snapshot: string, bookings: int|string, revenue: string}> $rows */
        $rows = BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.status', BookingStatus::Completed->value)
            ->where('bookings.completed_at', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy('booking_items.name_snapshot')
            ->orderByDesc(DB::raw('sum(booking_items.price_snapshot * booking_items.qty)'))
            ->limit($limit)
            ->select(DB::raw(
                'booking_items.name_snapshot,'
                .' count(distinct booking_items.booking_id) as bookings,'
                .' sum(booking_items.price_snapshot * booking_items.qty) as revenue'
            ))
            ->get()
            ->all();

        return array_map(fn (object $row): array => [
            'service' => $row->name_snapshot,
            'bookings' => (int) $row->bookings,
            'revenue' => round((float) $row->revenue, 2),
        ], $rows);
    }

    /**
     * Per-city trade (M25). A booking names its zone and a zone names its city,
     * so no column has to be kept in step — the grouping follows the same path
     * the zone gate walks.
     *
     * Every city is listed, including one that has sold nothing yet: a row of
     * zeroes is the answer to "how is the new city doing", and dropping it would
     * make a dead launch look like a missing one.
     *
     * @return list<array{id: int, name: string, is_active: bool, zones: int, bookings: int, gmv: float}>
     */
    public function byCity(int $days = 30): array
    {
        $cities = City::query()->withCount('zones')->ordered()->get();

        /** @var Collection<int, object{city_id: int, bookings: int|string, gmv: float|string|null}> $rows */
        $rows = Booking::query()
            ->join('zones', 'zones.id', '=', 'bookings.zone_id')
            ->where('bookings.created_at', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy('zones.city_id')
            ->selectRaw(
                'zones.city_id as city_id, count(*) as bookings,'
                .' coalesce(sum(case when bookings.status = ? then bookings.total else 0 end), 0) as gmv',
                [BookingStatus::Completed->value],
            )
            ->get()
            ->keyBy('city_id');

        return $cities
            ->map(fn (City $city): array => [
                'id' => $city->id,
                'name' => $city->name,
                'is_active' => $city->is_active,
                'zones' => (int) $city->zones_count,
                'bookings' => (int) ($rows[$city->id]->bookings ?? 0),
                'gmv' => round((float) ($rows[$city->id]->gmv ?? 0), 2),
            ])
            ->all();
    }

    /**
     * Providers ranked by completed jobs (recomputed M10 counters), with
     * money figures summed from their earnings rows.
     *
     * @return list<array{id: int, name: string, jobs_completed: int, rating_avg: float, rating_count: int, gross: float, net: float}>
     */
    public function providerLeaderboard(int $limit = 10): array
    {
        /** @var list<ProviderProfile> $profiles */
        $profiles = ProviderProfile::query()
            ->with('user:id,name')
            ->where('approval_status', ProviderApprovalStatus::Approved->value)
            ->orderByDesc('jobs_completed')
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get()
            ->all();

        $totals = Earning::query()
            ->whereIn('provider_id', array_map(fn (ProviderProfile $p): int => $p->user_id, $profiles))
            ->groupBy('provider_id')
            ->select(DB::raw('provider_id, sum(gross) as gross, sum(net) as net'))
            ->get()
            ->keyBy('provider_id');

        return array_map(fn (ProviderProfile $profile): array => [
            'id' => $profile->user_id,
            'name' => $profile->user->name,
            'jobs_completed' => (int) $profile->jobs_completed,
            'rating_avg' => (float) $profile->rating_avg,
            'rating_count' => (int) $profile->rating_count,
            'gross' => round((float) ($totals[$profile->user_id]->gross ?? 0), 2),
            'net' => round((float) ($totals[$profile->user_id]->net ?? 0), 2),
        ], $profiles);
    }

    /**
     * @param  callable(string): array<string, int|float>  $values
     * @return list<array<string, string|int|float>>
     */
    private function fillDays(Carbon $from, int $days, callable $values): array
    {
        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $day, ...$values($day)];
        }

        return $out;
    }
}
