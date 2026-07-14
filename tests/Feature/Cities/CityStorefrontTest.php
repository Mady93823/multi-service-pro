<?php

use App\Domain\Bookings\SlotGenerator;
use App\Domain\Cities\ActiveCity;
use App\Domain\Zones\ZoneResolver;
use App\Models\Address;
use App\Models\Booking;
use App\Models\City;
use App\Models\Service;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

test('the switcher records the city in the session', function () {
    $city = City::factory()->create(['name' => 'Switchville']);

    $this->from(route('home'))
        ->post(route('city.switch', $city))
        ->assertRedirect(route('home'))
        ->assertSessionHas(ActiveCity::SESSION_KEY, $city->id);
});

test('an inactive city cannot be switched into', function () {
    // Deactivating is the one control that closes a town — a bookmarked URL
    // must not walk around it.
    $city = City::factory()->inactive()->create();

    $this->post(route('city.switch', $city))->assertNotFound();
});

test('the active city is detected from the default address pin', function () {
    $city = City::factory()->create(['name' => 'Detected City']);
    $zone = Zone::factory()->for($city)->around(40.7128, -74.0060)->create();

    $customer = User::factory()->customer()->create();
    Address::factory()->for($customer)->default()->at(40.7128, -74.0060)->create(['zone_id' => $zone->id]);

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('site.active_city.name', 'Detected City'));
});

test('an explicit choice beats the detected city', function () {
    $home = City::factory()->create(['name' => 'Home City']);
    $zone = Zone::factory()->for($home)->around(40.7128, -74.0060)->create();
    $elsewhere = City::factory()->create(['name' => 'Elsewhere City']);

    $customer = User::factory()->customer()->create();
    Address::factory()->for($customer)->default()->at(40.7128, -74.0060)->create(['zone_id' => $zone->id]);

    $this->actingAs($customer)
        ->withSession([ActiveCity::SESSION_KEY => $elsewhere->id])
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('site.active_city.name', 'Elsewhere City'));
});

test('a deactivated city leaves the storefront but keeps its bookings', function () {
    $city = City::factory()->create(['name' => 'Closing City']);
    $zone = Zone::factory()->for($city)->around(40.7128, -74.0060)->create();
    $booking = Booking::factory()->create(['zone_id' => $zone->id]);

    $city->update(['is_active' => false]);

    // Off the switcher…
    $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('site.cities', fn (Collection $cities): bool => $cities->doesntContain('name', 'Closing City')));

    // …no longer resolving new address pins…
    expect(app(ZoneResolver::class)->resolve(40.7128, -74.0060))->toBeNull();

    // …and its history is untouched: money rows are snapshots (D33's rule).
    expect($booking->refresh()->zone_id)->toBe($zone->id)
        ->and(Zone::query()->whereKey($zone->id)->exists())->toBeTrue();
});

test('the slot grid is drawn in the city clock, not the platform one', function () {
    // Default platform timezone is Asia/Kolkata (+05:30) and the grid is hourly
    // from 08:00, so a New York 08:00 slot lands on a :30 and cannot be on it.
    $newYork = City::factory()->timezone('America/New_York')->create();
    $slots = app(SlotGenerator::class);

    $eightInNewYork = CarbonImmutable::tomorrow('America/New_York')->setTime(8, 0)->utc();

    expect($slots->isBookable($eightInNewYork, $newYork))->toBeTrue()
        ->and($slots->isBookable($eightInNewYork))->toBeFalse();

    $eightInIndia = CarbonImmutable::tomorrow('Asia/Kolkata')->setTime(8, 0)->utc();

    expect($slots->isBookable($eightInIndia))->toBeTrue()
        ->and($slots->isBookable($eightInIndia, $newYork))->toBeFalse();
});

test('the checkout slot picker follows the address city', function () {
    $city = City::factory()->timezone('America/New_York')->create(['name' => 'Slot City']);
    $zone = Zone::factory()->for($city)->around(40.7128, -74.0060)->create();

    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->default()->at(40.7128, -74.0060)->create(['zone_id' => $zone->id]);

    $service = Service::factory()->create();

    $this->actingAs($customer)->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->actingAs($customer)
        ->get(route('checkout.show', ['address' => $address->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('slot_city.timezone', 'America/New_York')
            ->where('slot_city.name', 'Slot City'));
});
