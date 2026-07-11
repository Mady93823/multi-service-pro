<?php

use App\Domain\Reports\DashboardMetrics;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EarningsFixtures;

/**
 * M13 gate: every dashboard figure must reconcile with a raw SQL aggregate
 * over the snapshot columns — on seeded data plus whatever the test adds.
 */
function rawValue(string $sql, array $bindings = []): float
{
    /** @var object{v: mixed}|null $row */
    $row = DB::selectOne($sql, $bindings);

    return round((float) ($row->v ?? 0), 2);
}

it('reconciles every KPI tile with raw SQL', function () {
    // Extra live data on top of the seeder so the window queries have bite.
    EarningsFixtures::complete(EarningsFixtures::booking());

    $tiles = app(DashboardMetrics::class)->tiles();

    expect($tiles['bookings_today'])->toBe((int) rawValue(
        'select count(*) v from bookings where date(created_at) = ? and deleted_at is null',
        [Carbon::today()->toDateString()],
    ));

    expect($tiles['bookings_week'])->toBe((int) rawValue(
        'select count(*) v from bookings where created_at >= ? and deleted_at is null',
        [Carbon::now()->subDays(7)],
    ));

    expect($tiles['gmv_month'])->toBe(rawValue(
        "select coalesce(sum(total), 0) v from bookings where status = 'completed' and completed_at >= ? and deleted_at is null",
        [Carbon::now()->startOfMonth()],
    ));

    expect($tiles['commission_month'])->toBe(rawValue(
        'select coalesce(sum(commission), 0) v from earnings where created_at >= ?',
        [Carbon::now()->startOfMonth()],
    ));

    expect($tiles['open_jobs'])->toBe((int) rawValue(
        "select count(*) v from bookings where status in ('assigned','accepted','en_route','arrived','in_progress') and deleted_at is null",
    ));

    expect($tiles['pending_payouts_amount'])->toBe(rawValue(
        "select coalesce(sum(amount), 0) v from payout_requests where status = 'requested'",
    ));

    expect($tiles['providers_online'])->toBe((int) rawValue(
        "select count(*) v from provider_profiles where approval_status = 'approved' and is_online = 1",
    ));

    expect($tiles['providers_pending_kyc'])->toBe((int) rawValue(
        "select count(*) v from provider_profiles where approval_status = 'pending'",
    ));
});

it('reconciles the 30-day revenue series with the earnings ledger', function () {
    EarningsFixtures::complete(EarningsFixtures::booking());

    $series = app(DashboardMetrics::class)->revenuePerDay();

    expect($series)->toHaveCount(30);

    $from = Carbon::today()->subDays(29);

    expect(round(array_sum(array_column($series, 'gross')), 2))->toBe(rawValue(
        'select coalesce(sum(gross), 0) v from earnings where created_at >= ?',
        [$from],
    ));

    expect(round(array_sum(array_column($series, 'commission')), 2))->toBe(rawValue(
        'select coalesce(sum(commission), 0) v from earnings where created_at >= ?',
        [$from],
    ));
});

it('reconciles the 30-day bookings series with the bookings table', function () {
    EarningsFixtures::booking();

    $series = app(DashboardMetrics::class)->bookingsPerDay();

    expect($series)->toHaveCount(30);

    expect(array_sum(array_column($series, 'bookings')))->toBe((int) rawValue(
        'select count(*) v from bookings where created_at >= ? and deleted_at is null',
        [Carbon::today()->subDays(29)],
    ));
});

it('reconciles top services revenue with raw item sums', function () {
    EarningsFixtures::complete(EarningsFixtures::booking());

    $top = app(DashboardMetrics::class)->topServices();

    expect($top)->not->toBeEmpty();

    expect(round(array_sum(array_column($top, 'revenue')), 2))->toBe(rawValue(
        "select coalesce(sum(bi.price_snapshot * bi.qty), 0) v
         from booking_items bi
         join bookings b on b.id = bi.booking_id
         where b.status = 'completed' and b.completed_at >= ?",
        [Carbon::today()->subDays(29)],
    ));
});

it('renders the dashboard with every metric prop', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/dashboard')
            ->has('tiles.bookings_today')
            ->has('tiles.gmv_month')
            ->has('bookings_per_day', 30)
            ->has('revenue_per_day', 30)
            ->has('top_services')
            ->has('leaderboard'));
});

it('blocks non-admins from the dashboard', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
});
