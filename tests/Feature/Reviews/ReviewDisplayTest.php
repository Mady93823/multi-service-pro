<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\Service;
use App\Models\User;
use Tests\Support\ReviewFixtures;

test('the service page shows visible reviews and the rating summary', function () {
    $service = Service::factory()->create(['price' => 500]);

    [$first, $firstCustomer] = ReviewFixtures::completedBooking($service);
    [$second, $secondCustomer] = ReviewFixtures::completedBooking($service);

    $this->actingAs($firstCustomer)->post(route('bookings.review.store', $first), [
        'rating' => 5,
        'comment' => 'Spotless.',
    ]);
    $this->actingAs($secondCustomer)->post(route('bookings.review.store', $second), ['rating' => 3]);

    auth()->logout();

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/show')
            ->count('reviews.data', 2)
            ->where('review_summary.count', 2)
            // A closure, not a literal: the prop crosses JSON, where 4.0 loses its decimal.
            ->where('review_summary.average', fn (int|float $average): bool => (float) $average === 4.0)
            ->where('review_summary.distribution.5', 1)
            ->where('review_summary.distribution.3', 1));
});

test('hidden reviews leave the service page and its summary', function () {
    $service = Service::factory()->create(['price' => 500]);
    [$booking, $customer] = ReviewFixtures::completedBooking($service);

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), ['rating' => 1]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reviews.hide', $booking->review()->sole()), ['reason' => 'Fake review.']);

    auth()->logout();

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))
        ->assertInertia(fn ($page) => $page
            ->count('reviews.data', 0)
            ->where('review_summary.count', 0));
});

test('the reviews section is absent while reviews are disabled', function () {
    app(SettingsRegistry::class)->set('reviews.enabled', false);
    $service = Service::factory()->create();

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reviews', null)
            ->where('review_summary', null));
});

test('the booking page offers the form only while a review is possible', function () {
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->get(route('bookings.show', $booking))
        ->assertInertia(fn ($page) => $page
            ->where('abilities.can_review', true)
            ->where('review', null));

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), ['rating' => 5]);

    $this->actingAs($customer)
        ->get(route('bookings.show', $booking))
        ->assertInertia(fn ($page) => $page
            ->where('abilities.can_review', false)
            ->where('review.rating', 5));
});

test('the provider dashboard lists their recent visible reviews', function () {
    [$booking, $customer, $provider] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), [
        'rating' => 5,
        'comment' => 'Wonderful.',
    ]);

    $this->actingAs($provider)
        ->get(route('provider.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('recent_reviews', 1)
            ->where('recent_reviews.0.rating', 5)
            ->where('recent_reviews.0.comment', 'Wonderful.'));
});
