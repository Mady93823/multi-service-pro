<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\WalletService;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;

/**
 * The customer dashboard had no test at all, which is how it shipped as the
 * Phase 1 WebSocket smoke test — a "Send test broadcast" button — and stayed
 * the customer's home screen for sixteen modules.
 *
 * The database is seeded, so every assertion here scopes to a customer this
 * file creates. Never a global count (landmine 6).
 */
function dashboardCustomer(): User
{
    return User::factory()->customer()->create();
}

test('a customer with no history is offered the one thing this page can do for them', function () {
    $this->actingAs(dashboardCustomer())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/dashboard')
            ->where('live', null)
            ->count('awaiting_payment', 0)
            ->count('upcoming', 0)
            ->count('recent', 0)
            ->where('stats.completed', 0)
            ->where('stats.upcoming', 0));
});

test('a provider on the way is the headline, not a row in a list', function () {
    $customer = dashboardCustomer();
    $provider = User::factory()->provider()->create();

    Booking::factory()
        ->for($customer, 'customer')
        ->withProvider($provider)
        ->status(BookingStatus::EnRoute)
        ->create();

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('live.status', BookingStatus::EnRoute->value)
            ->where('live.provider.name', $provider->name)
            // and it is not repeated underneath itself
            ->count('upcoming', 0));
});

test('an unpaid booking is surfaced, because it expires on its own', function () {
    $customer = dashboardCustomer();

    $booking = Booking::factory()
        ->for($customer, 'customer')
        ->status(BookingStatus::PendingPayment)
        ->create();

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->count('awaiting_payment', 1)
            ->where('awaiting_payment.0.id', $booking->id)
            // It is open, so it counts as upcoming in the tiles — but it must not
            // sit in the upcoming *list*, where it would read as confirmed.
            ->count('upcoming', 0)
            ->where('stats.upcoming', 1));
});

test('a completed booking asks for a review until it has one', function () {
    $customer = dashboardCustomer();
    $provider = User::factory()->provider()->create();

    $booking = Booking::factory()
        ->for($customer, 'customer')
        ->withProvider($provider)
        ->status(BookingStatus::Completed)
        ->create(['completed_at' => now()]);

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->count('to_review', 1)
            ->where('to_review.0.id', $booking->id)
            ->where('stats.completed', 1));

    Review::factory()->create([
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'provider_id' => $provider->id,
    ]);

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->count('to_review', 0));
});

test('a cancelled booking is history, never upcoming', function () {
    $customer = dashboardCustomer();

    Booking::factory()
        ->for($customer, 'customer')
        ->status(BookingStatus::CancelledCustomer)
        ->create(['cancelled_at' => now()]);

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->count('upcoming', 0)
            ->count('recent', 1)
            ->where('stats.upcoming', 0)
            ->where('stats.completed', 0));
});

test('the dashboard shows this customer their own bookings and nobody else, including the seeded ones', function () {
    $customer = dashboardCustomer();
    $stranger = dashboardCustomer();

    Booking::factory()->for($stranger, 'customer')->status(BookingStatus::Placed)->create();
    $mine = Booking::factory()->for($customer, 'customer')->status(BookingStatus::Placed)->create();

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->count('upcoming', 1)
            ->where('upcoming.0.id', $mine->id)
            ->where('stats.upcoming', 1));
});

test('the wallet balance on the tile is this customer’s own', function () {
    $customer = dashboardCustomer();

    app(WalletService::class)->credit($customer, '250.00', 'topup');

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.wallet_balance', '250.00'));
});
