<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\ProviderProfile;
use App\Models\User;

function jobProvider(): User
{
    $user = User::factory()->provider()->create();
    ProviderProfile::factory()->approved()->online()->for($user)->create([
        'base_lat' => 40.7128,
        'base_lng' => -74.006,
        'service_radius_km' => 20,
    ]);

    return $user;
}

function jobOfferFor(User $provider): DispatchOffer
{
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();

    return $booking->dispatchOffers()->create([
        'provider_id' => $provider->id,
        'strategy' => 'nearest',
        'status' => OfferStatus::Offered->value,
        'round' => 1,
        'offered_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);
}

test('the jobs screen shows the provider their offers and active jobs', function () {
    $provider = jobProvider();
    jobOfferFor($provider);
    Booking::factory()->status(BookingStatus::Assigned)->withProvider($provider)->create();

    // Another provider's offer must not leak in.
    jobOfferFor(jobProvider());

    $this->actingAs($provider)
        ->get(route('provider.jobs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('provider/jobs')
            ->has('offers', 1)
            ->has('jobs', 1));
});

test('a provider can accept an offer and it assigns the booking', function () {
    $provider = jobProvider();
    $offer = jobOfferFor($provider);

    $this->actingAs($provider)
        ->post(route('provider.offers.accept', $offer))
        ->assertRedirect();

    $offer->refresh();
    expect($offer->status)->toBe(OfferStatus::Accepted)
        ->and($offer->booking->status)->toBe(BookingStatus::Accepted)
        ->and($offer->booking->provider_id)->toBe($provider->id);
});

test('a provider can decline an offer', function () {
    $provider = jobProvider();
    $offer = jobOfferFor($provider);

    $this->actingAs($provider)
        ->post(route('provider.offers.decline', $offer))
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Declined);
});

test('a provider advances a job through the happy path with the start code', function () {
    $provider = jobProvider();
    $booking = Booking::factory()
        ->status(BookingStatus::Assigned)
        ->withProvider($provider)
        ->create(['job_otp_code' => '1234']);

    $this->actingAs($provider);

    $this->post(route('provider.jobs.advance', $booking), ['to' => 'accepted'])->assertRedirect();
    $this->post(route('provider.jobs.advance', $booking), ['to' => 'en_route'])->assertRedirect();
    $this->post(route('provider.jobs.advance', $booking), ['to' => 'arrived'])->assertRedirect();

    // Starting the job needs the customer's code.
    $this->post(route('provider.jobs.advance', $booking), ['to' => 'in_progress'])
        ->assertSessionHasErrors('otp');
    $this->post(route('provider.jobs.advance', $booking), ['to' => 'in_progress', 'otp' => '0000'])
        ->assertSessionHasErrors('otp');
    $this->post(route('provider.jobs.advance', $booking), ['to' => 'in_progress', 'otp' => '1234'])
        ->assertSessionHasNoErrors();

    $this->post(route('provider.jobs.advance', $booking), ['to' => 'completed'])->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('a provider cannot advance a booking that is not theirs', function () {
    $provider = jobProvider();
    $booking = Booking::factory()
        ->status(BookingStatus::Assigned)
        ->withProvider(jobProvider())
        ->create();

    $this->actingAs($provider)
        ->post(route('provider.jobs.advance', $booking), ['to' => 'accepted'])
        ->assertNotFound();
});

test('an open offer never carries the doorstep — city only, no pin, no phone', function () {
    $provider = jobProvider();
    jobOfferFor($provider);

    // D41: the offer payload (not just the card) must stay city-only —
    // Inertia serializes every prop into the page HTML.
    $this->actingAs($provider)
        ->get(route('provider.jobs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('offers.0.booking.address.city')
            ->missing('offers.0.booking.address.line1')
            ->missing('offers.0.booking.address.lat')
            ->missing('offers.0.booking.address.lng')
            ->missing('offers.0.booking.contact_phone'));
});

test('an accepted job carries the address pin the navigate link needs', function () {
    $provider = jobProvider();
    Booking::factory()->status(BookingStatus::Accepted)->withProvider($provider)->create();

    // D44: once the job is taken, the doorstep is the provider's to reach.
    $this->actingAs($provider)
        ->get(route('provider.jobs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('jobs.0.address.lat')
            ->has('jobs.0.address.lng')
            ->has('jobs.0.contact_phone'));
});

test('an unapproved provider is bounced from the jobs screen', function () {
    $user = User::factory()->provider()->create();
    ProviderProfile::factory()->for($user)->create(); // pending

    $this->actingAs($user)
        ->get(route('provider.jobs.index'))
        ->assertRedirect(route('provider.onboarding'));
});
