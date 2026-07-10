<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReviewFixtures;

test('a customer can review their completed booking and the provider rating updates', function () {
    [$booking, $customer, $provider] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), [
            'rating' => 4,
            'comment' => 'Solid work, arrived on time.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $review = $booking->review()->sole();

    expect($review->rating)->toBe(4)
        ->and($review->customer_id)->toBe($customer->id)
        ->and($review->provider_id)->toBe($provider->id)
        ->and($review->is_hidden)->toBeFalse();

    $profile = $provider->providerProfile()->sole();

    expect((float) $profile->rating_avg)->toBe(4.0)
        ->and($profile->rating_count)->toBe(1);
});

test('the average blends every visible review for the provider', function () {
    [$first, $customer, $provider] = ReviewFixtures::completedBooking();
    $this->actingAs($customer)->post(route('bookings.review.store', $first), ['rating' => 5]);

    // A second completed job for the same provider, different customer.
    [$second] = ReviewFixtures::completedBooking();
    $second->forceFill(['provider_id' => $provider->id])->save();
    $other = User::query()->findOrFail($second->customer_id);

    $this->actingAs($other)->post(route('bookings.review.store', $second), ['rating' => 2])
        ->assertSessionHasNoErrors();

    $profile = $provider->providerProfile()->sole();

    expect((float) $profile->rating_avg)->toBe(3.5)
        ->and($profile->rating_count)->toBe(2);
});

test('a booking can only be reviewed once', function () {
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), ['rating' => 5]);

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), ['rating' => 1])
        ->assertSessionHasErrors('rating');

    expect($booking->review()->count())->toBe(1)
        ->and($booking->review()->sole()->rating)->toBe(5);
});

test('a booking that is not completed cannot be reviewed', function () {
    [$booking, $customer] = ReviewFixtures::completedBooking();
    $booking->forceFill(['status' => 'in_progress'])->save();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), ['rating' => 5])
        ->assertForbidden();
});

test('only the booking customer can review it', function () {
    [$booking] = ReviewFixtures::completedBooking();
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->post(route('bookings.review.store', $booking), ['rating' => 5])
        ->assertForbidden();
});

test('guests are sent to login', function () {
    [$booking] = ReviewFixtures::completedBooking();

    $this->post(route('bookings.review.store', $booking), ['rating' => 5])
        ->assertRedirect(route('login'));
});

test('rating must be one to five', function (int $rating) {
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), ['rating' => $rating])
        ->assertSessionHasErrors('rating');
})->with([0, 6]);

test('review photos land on the private disk within the settings limit', function () {
    Storage::fake('local');
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), [
            'rating' => 5,
            'photos' => [
                UploadedFile::fake()->image('after-1.jpg'),
                UploadedFile::fake()->image('after-2.jpg'),
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($booking->review()->sole()->getMedia('review_photos'))->toHaveCount(2);
});

test('more photos than reviews.max_photos allows is rejected', function () {
    Storage::fake('local');
    app(SettingsRegistry::class)->set('reviews.max_photos', 1);
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), [
            'rating' => 5,
            'photos' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ])
        ->assertSessionHasErrors('photos');
});

test('submitting is blocked while reviews are disabled', function () {
    app(SettingsRegistry::class)->set('reviews.enabled', false);
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)
        ->post(route('bookings.review.store', $booking), ['rating' => 5])
        ->assertForbidden();

    // Booking-scoped: the demo seeder runs per test and seeds reviews of its own.
    expect($booking->review()->exists())->toBeFalse();
});

test('the provider is notified of a new review', function () {
    Notification::fake();
    [$booking, $customer, $provider] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), ['rating' => 5]);

    Notification::assertSentTo(
        $provider,
        ReviewReceivedNotification::class,
        fn (ReviewReceivedNotification $notification): bool => $notification->review->booking_id === $booking->id,
    );
});
