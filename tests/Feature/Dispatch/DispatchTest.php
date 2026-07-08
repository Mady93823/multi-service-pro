<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Dispatch\Actions\AcceptOffer;
use App\Domain\Dispatch\Actions\DeclineOffer;
use App\Domain\Dispatch\Actions\DispatchBooking;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Domain\Dispatch\Events\DispatchExhausted;
use App\Domain\Dispatch\Jobs\ExpireDispatchRound;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Category;
use App\Models\DispatchOffer;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;

// NYC coords match BookingFactory's default address; the seeded Bengaluru demo
// provider is ~12,000 km away and serves catalog categories, so a fresh
// category here keeps every scenario down to just the providers we create.
function dispatchCategoryService(): Service
{
    $category = Category::factory()->create(['is_active' => true]);

    return Service::factory()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 500]);
}

function dispatchProvider(Service $service, float $lat = 40.7128, float $lng = -74.006, int $radius = 20, bool $online = true): User
{
    $user = User::factory()->provider()->create();

    $builder = ProviderProfile::factory()->approved()->for($user);
    if ($online) {
        $builder = $builder->online();
    }

    $profile = $builder->create([
        'base_lat' => $lat,
        'base_lng' => $lng,
        'service_radius_km' => $radius,
    ]);
    $profile->categories()->sync([$service->category_id]);

    return $user;
}

function dispatchBooking(Service $service, BookingStatus $status = BookingStatus::Placed): Booking
{
    $booking = Booking::factory()->status($status)->create();
    $booking->items()->create([
        'service_id' => $service->id,
        'name_snapshot' => $service->name,
        'price_snapshot' => $service->price,
        'qty' => 1,
        'addons_snapshot' => [],
    ]);

    return $booking;
}

test('dispatch offers the nearest online provider and moves to searching', function () {
    $service = dispatchCategoryService();
    $near = dispatchProvider($service, 40.7128, -74.006);         // on top of the address
    $far = dispatchProvider($service, 40.75, -74.006);            // ~4 km north

    $booking = dispatchBooking($service);

    app(DispatchBooking::class)->handle($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Searching);

    $offers = DispatchOffer::query()->where('booking_id', $booking->id)->get();
    expect($offers)->toHaveCount(1)
        ->and($offers->first()->provider_id)->toBe($near->id)
        ->and($offers->first()->provider_id)->not->toBe($far->id);
});

test('an offline provider gets no offer and the booking stays placed', function () {
    $service = dispatchCategoryService();
    dispatchProvider($service, online: false);

    $booking = dispatchBooking($service);

    app(DispatchBooking::class)->handle($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Placed)
        ->and(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

test('a provider outside their service radius is skipped', function () {
    $service = dispatchCategoryService();
    dispatchProvider($service, 41.16, -74.006, radius: 5); // ~50 km away, radius 5

    $booking = dispatchBooking($service);

    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

test('a provider who does not serve the category is skipped', function () {
    $service = dispatchCategoryService();
    $otherService = dispatchCategoryService();
    dispatchProvider($otherService); // serves a different category

    $booking = dispatchBooking($service);

    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

test('a provider on blackout that day is skipped', function () {
    $service = dispatchCategoryService();
    $provider = dispatchProvider($service);

    $booking = dispatchBooking($service); // scheduled two days out (factory default)
    $day = $booking->scheduled_at->toImmutable();

    $provider->providerProfile->blackouts()->create([
        'starts_on' => $day->subDay()->toDateString(),
        'ends_on' => $day->addDay()->toDateString(),
        'reason' => 'Away',
    ]);

    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

test('a provider already on an overlapping job is skipped', function () {
    $service = dispatchCategoryService();
    $provider = dispatchProvider($service);

    $booking = dispatchBooking($service);

    // Existing accepted job on the same slot.
    Booking::factory()
        ->status(BookingStatus::Accepted)
        ->withProvider($provider)
        ->scheduledAt($booking->scheduled_at->toImmutable())
        ->create();

    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
});

test('accepting an offer assigns the booking and expires the siblings', function () {
    app(SettingsRegistry::class)->set('dispatch.mode', 'broadcast');

    $service = dispatchCategoryService();
    $winner = dispatchProvider($service, 40.7128, -74.006);
    $loser = dispatchProvider($service, 40.73, -74.006);

    $booking = dispatchBooking($service);
    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(2);

    $winnerOffer = DispatchOffer::query()->where('booking_id', $booking->id)->where('provider_id', $winner->id)->sole();

    app(AcceptOffer::class)->handle($winnerOffer, $winner);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Accepted)
        ->and($booking->provider_id)->toBe($winner->id);

    $loserOffer = DispatchOffer::query()->where('booking_id', $booking->id)->where('provider_id', $loser->id)->sole();
    expect($loserOffer->status)->toBe(OfferStatus::Expired)
        ->and($winnerOffer->fresh()->status)->toBe(OfferStatus::Accepted);

    expect($booking->statusHistory()->orderBy('id')->pluck('to_status')->all())
        ->toContain('searching', 'assigned', 'accepted');
});

test('declining re-offers the next nearest provider', function () {
    $service = dispatchCategoryService();
    $first = dispatchProvider($service, 40.7128, -74.006);
    $second = dispatchProvider($service, 40.75, -74.006);

    $booking = dispatchBooking($service);
    app(DispatchBooking::class)->handle($booking);

    $firstOffer = DispatchOffer::query()->where('booking_id', $booking->id)->sole();
    expect($firstOffer->provider_id)->toBe($first->id);

    app(DeclineOffer::class)->handle($firstOffer, $first);

    expect($firstOffer->fresh()->status)->toBe(OfferStatus::Declined);

    $secondOffer = DispatchOffer::query()->where('booking_id', $booking->id)->where('status', OfferStatus::Offered->value)->sole();
    expect($secondOffer->provider_id)->toBe($second->id)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Searching);
});

test('the timeout job expires the round and re-offers the next provider', function () {
    app(SettingsRegistry::class)->set('dispatch.offer_timeout_seconds', 60);

    $service = dispatchCategoryService();
    $first = dispatchProvider($service, 40.7128, -74.006);
    $second = dispatchProvider($service, 40.75, -74.006);

    $booking = dispatchBooking($service);
    app(DispatchBooking::class)->handle($booking);

    $firstOffer = DispatchOffer::query()->where('booking_id', $booking->id)->sole();
    expect($firstOffer->provider_id)->toBe($first->id)
        ->and($firstOffer->status)->toBe(OfferStatus::Offered); // guard kept it open under sync

    $this->travelTo(CarbonImmutable::now()->addSeconds(61));

    (new ExpireDispatchRound($booking->id, 1))->handle(app(DispatchBooking::class));

    expect($firstOffer->fresh()->status)->toBe(OfferStatus::Expired);

    $secondOffer = DispatchOffer::query()->where('booking_id', $booking->id)->where('status', OfferStatus::Offered->value)->sole();
    expect($secondOffer->provider_id)->toBe($second->id);

    $this->travelBack();
});

test('dispatch exhausts and alerts when a searching booking has no candidates', function () {
    Event::fake([DispatchExhausted::class]);

    $service = dispatchCategoryService(); // no providers created

    $booking = dispatchBooking($service, BookingStatus::Searching);

    app(DispatchBooking::class)->handle($booking);

    expect(DispatchOffer::query()->where('booking_id', $booking->id)->count())->toBe(0);
    Event::assertDispatched(DispatchExhausted::class);
});

test('placing a booking auto-dispatches to an eligible provider', function () {
    $service = dispatchCategoryService();
    $provider = dispatchProvider($service);

    $booking = dispatchBooking($service);

    event(new BookingPlaced($booking));

    expect($booking->fresh()->status)->toBe(BookingStatus::Searching)
        ->and(DispatchOffer::query()->where('booking_id', $booking->id)->where('provider_id', $provider->id)->count())->toBe(1);
});
