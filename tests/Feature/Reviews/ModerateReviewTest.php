<?php

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReviewFixtures;

function reviewedBooking(int $rating = 5): array
{
    [$booking, $customer, $provider] = ReviewFixtures::completedBooking();

    test()->actingAs($customer)->post(route('bookings.review.store', $booking), [
        'rating' => $rating,
        'comment' => 'Review under test.',
    ]);

    return [$booking->review()->sole(), $customer, $provider];
}

test('an admin can hide a review with a reason and the rating recomputes', function () {
    [$review, , $provider] = reviewedBooking(1);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reviews.hide', $review), ['reason' => 'Abusive language.'])
        ->assertRedirect();

    $review->refresh();

    expect($review->is_hidden)->toBeTrue()
        ->and($review->hidden_reason)->toBe('Abusive language.');

    // The hidden 1-star must stop dragging the average down.
    $profile = $provider->providerProfile()->sole();

    expect((float) $profile->rating_avg)->toBe(0.0)
        ->and($profile->rating_count)->toBe(0);
});

test('hiding requires a reason', function () {
    [$review] = reviewedBooking();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reviews.hide', $review), [])
        ->assertSessionHasErrors('reason');

    expect($review->refresh()->is_hidden)->toBeFalse();
});

test('unhiding restores the review and the rating', function () {
    [$review, , $provider] = reviewedBooking(4);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.reviews.hide', $review), ['reason' => 'Checking something.']);
    $this->actingAs($admin)->post(route('admin.reviews.unhide', $review))->assertRedirect();

    $review->refresh();

    expect($review->is_hidden)->toBeFalse()
        ->and($review->hidden_reason)->toBeNull();

    $profile = $provider->providerProfile()->sole();

    expect((float) $profile->rating_avg)->toBe(4.0)
        ->and($profile->rating_count)->toBe(1);
});

test('non-admins cannot moderate', function () {
    [$review, $customer] = reviewedBooking();

    $this->actingAs($customer)
        ->post(route('admin.reviews.hide', $review), ['reason' => 'Nope.'])
        ->assertForbidden();
});

test('the admin moderation queue lists and filters reviews', function () {
    [$review] = reviewedBooking(2);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.reviews.hide', $review), ['reason' => 'Spam.']);

    $this->actingAs($admin)
        ->get(route('admin.reviews.index', ['visibility' => 'hidden']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reviews/index')
            ->count('reviews.data', 1)
            ->where('reviews.data.0.id', $review->id)
            ->where('reviews.data.0.hidden_reason', 'Spam.'));

    // The seeder's own (visible) reviews are in the list — assert the hidden
    // one is filtered out rather than pinning a count.
    $this->actingAs($admin)
        ->get(route('admin.reviews.index', ['visibility' => 'visible']))
        ->assertInertia(fn ($page) => $page
            ->where('reviews.data', fn ($data) => collect($data)->pluck('id')->doesntContain($review->id)));
});

test('hidden review photos vanish for the public but stay for the owner and admins', function () {
    Storage::fake('local');
    [$booking, $customer] = ReviewFixtures::completedBooking();

    $this->actingAs($customer)->post(route('bookings.review.store', $booking), [
        'rating' => 5,
        'photos' => [UploadedFile::fake()->image('proof.jpg')],
    ]);

    $review = $booking->review()->sole();
    $media = $review->getMedia('review_photos')->first();
    $admin = User::factory()->admin()->create();

    // Visible: even a guest can load it (the storefront shows it).
    auth()->logout();
    $this->get(route('reviews.photos.show', [$review, $media]))->assertOk();

    $this->actingAs($admin)->post(route('admin.reviews.hide', $review), ['reason' => 'Personal data in photo.']);

    auth()->logout();
    $this->get(route('reviews.photos.show', [$review, $media]))->assertNotFound();

    $this->actingAs($customer)->get(route('reviews.photos.show', [$review, $media]))->assertOk();
    $this->actingAs($admin)->get(route('reviews.photos.show', [$review, $media]))->assertOk();
});

test('a photo from another review 404s even when the review is visible', function () {
    Storage::fake('local');
    [$first, $firstCustomer] = ReviewFixtures::completedBooking();
    [$second, $secondCustomer] = ReviewFixtures::completedBooking();

    $this->actingAs($firstCustomer)->post(route('bookings.review.store', $first), [
        'rating' => 5,
        'photos' => [UploadedFile::fake()->image('mine.jpg')],
    ]);
    $this->actingAs($secondCustomer)->post(route('bookings.review.store', $second), ['rating' => 4]);

    $media = $first->review()->sole()->getMedia('review_photos')->first();
    $otherReview = $second->review()->sole();

    $this->get(route('reviews.photos.show', [$otherReview, $media]))->assertNotFound();
});

test('hiding never deletes the row — the customer keeps their review', function () {
    [$review, $customer] = reviewedBooking();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reviews.hide', $review), ['reason' => 'Moderated.']);

    // Row still there — scoped to the booking, the seeder adds reviews of its own.
    expect(Review::query()->whereKey($review->id)->exists())->toBeTrue();

    $this->actingAs($customer)
        ->get(route('bookings.show', $review->booking_id))
        ->assertInertia(fn ($page) => $page
            ->where('review.id', $review->id)
            ->where('review.is_hidden', true)
            ->where('review.hidden_reason', 'Moderated.')
            ->where('abilities.can_review', false));
});
