<?php

use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An N+1 is invisible on seeded data and fatal on real data: the admin bookings
 * list is fine with twelve rows and issues three hundred queries with a hundred.
 * `Model::preventLazyLoading()` catches the common shape, but not every one — an
 * explicit `->load()` inside a loop, or a `->count()` per row, is a hand-written
 * N+1 that no lazy-load guard sees.
 *
 * So the assertion is not "fewer than N queries" (a number that rots and that
 * nobody can justify). It is: **the query count does not grow when the data
 * does.** That is the actual property we want, it needs no magic constant, and
 * it fails on the day someone adds a per-row lookup. Going *down* is allowed and
 * happens for honest reasons — Eloquent skips an eager-load query entirely when
 * no parent row has a key to load.
 */

/** Run the request with a warm cache and count the queries it costs. */
function queriesFor(callable $request): int
{
    // Warm first: the settings registry, the menus and the footer pages are
    // cached read models, and their first read would otherwise be counted as
    // growth. We are measuring what scales, not what boots.
    $request();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $request();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}

test('a lazy load is a test failure, not a slow page', function () {
    // The guard itself has to be guarded: switching it off would make every test
    // below pass a little faster and prove nothing. It stays off in production —
    // a violation there must degrade to a slow page, never to a 500 in front of
    // a customer.
    expect(Model::preventsLazyLoading())->toBeTrue();
});

test('the admin bookings list does not query per booking', function () {
    $admin = User::factory()->admin()->create();
    $visit = fn () => $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();

    Booking::factory()->count(3)->create();
    $small = queriesFor($visit);

    Booking::factory()->count(20)->create();
    $large = queriesFor($visit);

    expect($large)->toBeLessThanOrEqual(
        $small,
        "The bookings list costs {$small} queries for 3 bookings and {$large} for 23 — it queries per row.",
    );
});

test('the admin payments list does not query per payment', function () {
    $admin = User::factory()->admin()->create();
    $visit = fn () => $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();

    Booking::factory()->count(3)->create()->each(fn (Booking $booking) => $booking->payments()->create([
        'gateway' => 'razorpay',
        'amount' => $booking->total,
        'currency' => 'INR',
        'status' => 'captured',
    ]));

    $small = queriesFor($visit);

    Booking::factory()->count(15)->create()->each(fn (Booking $booking) => $booking->payments()->create([
        'gateway' => 'razorpay',
        'amount' => $booking->total,
        'currency' => 'INR',
        'status' => 'captured',
    ]));

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});

test('the admin customers list does not query per customer', function () {
    $admin = User::factory()->admin()->create();
    $visit = fn () => $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk();

    User::factory()->customer()->count(3)->create();
    $small = queriesFor($visit);

    User::factory()->customer()->count(20)->create();

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});

test('the storefront catalog does not query per service', function () {
    // The busiest page on the site, and the one a guest hits first.
    $visit = fn () => $this->get(route('catalog.index'))->assertOk();

    Service::factory()->count(3)->create();
    $small = queriesFor($visit);

    Service::factory()->count(20)->create();

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});

test('the home page does not query per block', function () {
    // Home is a page of blocks (M20), each one resolving its own models — the
    // exact shape that grows a query per block if a block forgets to eager-load.
    $visit = fn () => $this->get(route('home'))->assertOk();

    $small = queriesFor($visit);

    Service::factory()->count(15)->create();
    Booking::factory()->count(10)->create();

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});

test('the blog index does not query per post', function () {
    $visit = fn () => $this->get(route('blog.index'))->assertOk();

    BlogPost::factory()->count(3)->create();
    $small = queriesFor($visit);

    BlogPost::factory()->count(12)->create();

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});

test('a customer’s own bookings list does not query per booking', function () {
    $customer = User::factory()->customer()->create();
    $visit = fn () => $this->actingAs($customer)->get(route('bookings.index'))->assertOk();

    Booking::factory()->count(3)->create(['customer_id' => $customer->id]);
    $small = queriesFor($visit);

    Booking::factory()->count(15)->create(['customer_id' => $customer->id]);

    expect(queriesFor($visit))->toBeLessThanOrEqual($small);
});
