<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Tracking\Enums\TrackingSessionStatus;
use App\Domain\Tracking\Events\LocationUpdated;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function trackProvider(): User
{
    $user = User::factory()->provider()->create();
    ProviderProfile::factory()->approved()->online()->for($user)->create([
        'base_lat' => 40.7128,
        'base_lng' => -74.006,
        'service_radius_km' => 20,
    ]);

    return $user;
}

function trackBooking(User $provider, BookingStatus $status = BookingStatus::Accepted): Booking
{
    return Booking::factory()->status($status)->withProvider($provider)->create();
}

test('starting a journey moves the booking en route and opens a session', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider)
        ->post(route('provider.tracking.start', $booking))
        ->assertOk()
        ->assertJsonPath('status', 'en_route');

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::EnRoute)
        ->and($booking->trackingSessions()->count())->toBe(1)
        ->and($booking->trackingSessions()->first()->status)->toBe(TrackingSessionStatus::Active);
});

test('starting twice reuses the live session instead of stacking another', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();
    $this->post(route('provider.tracking.start', $booking))->assertOk();

    expect($booking->trackingSessions()->count())->toBe(1);
});

test('a ping stores a point, moves the checkpoint and broadcasts', function () {
    Event::fake([LocationUpdated::class]);

    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();

    $this->postJson(route('provider.tracking.ping', $booking), [
        'lat' => 40.7130,
        'lng' => -74.0050,
        'accuracy' => 12.5,
        'heading' => 90,
        'speed' => 18.4,
    ])->assertOk()->assertJsonPath('dropped', false);

    $session = $booking->trackingSessions()->first();

    expect(TrackingPoint::query()->count())->toBe(1)
        ->and((float) $session->last_lat)->toBe(40.713)
        ->and((float) $session->last_speed_kmh)->toBe(18.4)
        ->and($session->last_ping_at)->not->toBeNull();

    Event::assertDispatched(LocationUpdated::class);
});

test('a wildly inaccurate fix is dropped rather than smeared onto the map', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();

    $this->postJson(route('provider.tracking.ping', $booking), [
        'lat' => 40.7130,
        'lng' => -74.0050,
        'accuracy' => 350,
    ])->assertOk()->assertJsonPath('dropped', true);

    expect(TrackingPoint::query()->count())->toBe(0);
});

test('a dead reverb degrades tracking instead of failing the provider ping', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();

    // Point the broadcaster at a closed port: the ping must still persist.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => [],
        ],
    ]);

    $this->postJson(route('provider.tracking.ping', $booking), ['lat' => 40.7130, 'lng' => -74.0050])
        ->assertOk()
        ->assertJsonPath('dropped', false);

    expect(TrackingPoint::query()->count())->toBe(1);
});

test('pings are refused unless the booking is on an active journey', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider); // accepted, never started

    $this->actingAs($provider)
        ->postJson(route('provider.tracking.ping', $booking), ['lat' => 40.71, 'lng' => -74.0])
        ->assertStatus(409);
});

test('a ping is rejected with an out-of-range coordinate', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();

    $this->postJson(route('provider.tracking.ping', $booking), ['lat' => 120, 'lng' => -74.0])
        ->assertStatus(422);
});

test('a provider cannot ping a booking that is not theirs', function () {
    $provider = trackProvider();
    $booking = trackBooking(trackProvider());

    $this->actingAs($provider)
        ->postJson(route('provider.tracking.ping', $booking), ['lat' => 40.71, 'lng' => -74.0])
        ->assertNotFound();
});

test('arriving ends the session and freezes the map', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();
    $this->post(route('provider.tracking.stop', $booking))->assertOk()->assertJsonPath('status', 'arrived');

    $booking->refresh();
    $session = $booking->trackingSessions()->first();

    expect($booking->status)->toBe(BookingStatus::Arrived)
        ->and($session->status)->toBe(TrackingSessionStatus::Ended)
        ->and($session->ended_at)->not->toBeNull();
});

test('the customer can read the last checkpoint for the polling fallback', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();
    $this->postJson(route('provider.tracking.ping', $booking), ['lat' => 40.7130, 'lng' => -74.0050])->assertOk();

    $this->actingAs($booking->customer)
        ->getJson(route('tracking.last', $booking))
        ->assertOk()
        ->assertJsonPath('booking_status', 'en_route')
        ->assertJsonPath('session_status', 'active')
        ->assertJsonPath('lat', 40.713);
});

test('a stranger cannot read another booking checkpoint', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs(User::factory()->customer()->create())
        ->getJson(route('tracking.last', $booking))
        ->assertForbidden();
});

test('the journey screen renders for an accepted job', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider)
        ->get(route('provider.jobs.journey', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('provider/journey')
            ->where('booking.id', $booking->id)
            ->has('config.ping_interval_seconds'));
});

test('the journey screen bounces a job that is past arrival', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider, BookingStatus::InProgress);

    $this->actingAs($provider)
        ->get(route('provider.jobs.journey', $booking))
        ->assertRedirect(route('provider.jobs.index'));
});

/**
 * Channel authorization runs through the real broadcaster, so point the default
 * driver at reverb with throwaway credentials (auth is local HMAC — no network).
 * Channels are registered against whichever driver was default at boot (the
 * null one), so routes/channels.php has to be replayed onto the new driver.
 */
function trackUseReverbDriver(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => [],
        ],
    ]);

    require base_path('routes/channels.php');
}

test('the booking customer may subscribe to its tracking channel', function () {
    trackUseReverbDriver();
    $booking = trackBooking(trackProvider());

    $this->actingAs($booking->customer)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-tracking.booking.'.$booking->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('another customer is refused on someone elses tracking channel', function () {
    trackUseReverbDriver();
    $booking = trackBooking(trackProvider());

    $this->actingAs(User::factory()->customer()->create())
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-tracking.booking.'.$booking->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

test('pruning drops tracking points past the retention window', function () {
    $provider = trackProvider();
    $booking = trackBooking($provider);

    $this->actingAs($provider);
    $this->post(route('provider.tracking.start', $booking))->assertOk();
    $session = $booking->trackingSessions()->first();

    $session->points()->create(['lat' => 1, 'lng' => 1, 'recorded_at' => now()->subDays(45)]);
    $session->points()->create(['lat' => 2, 'lng' => 2, 'recorded_at' => now()->subDay()]);

    $this->artisan('tracking:prune')->assertSuccessful();

    expect(TrackingPoint::query()->count())->toBe(1);
});
