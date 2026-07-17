<?php

use App\Domain\Cities\ActiveCity;
use App\Models\Category;
use App\Models\City;
use App\Models\Service;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia;

// "Use my location" (M25/M03). Coordinates/names avoid the seeded demo zones
// (Bengaluru) — the suite seeds DatabaseSeeder before every test.

test('use my location resolves a pin to its zone and city', function () {
    $city = City::factory()->create(['name' => 'Pin City']);
    $zone = Zone::factory()->for($city)->around(40.7128, -74.0060)->create(['name' => 'Pin Zone']);

    $this->from(route('home'))
        ->post(route('location.detect'), ['lat' => 40.7128, 'lng' => -74.0060])
        ->assertRedirect(route('home'))
        ->assertSessionHas(ActiveCity::SESSION_KEY, $city->id)
        ->assertSessionHas(ActiveCity::ZONE_SESSION_KEY, $zone->id);
});

test('a pin outside every area selects the nearest zone and its city', function () {
    $near = City::factory()->create(['name' => 'Near City']);
    $nearZone = Zone::factory()->for($near)->around(40.7128, -74.0060)->create();

    $far = City::factory()->create(['name' => 'Far City']);
    Zone::factory()->for($far)->around(51.5074, -0.1278)->create();

    $this->post(route('location.detect'), ['lat' => 39.9, 'lng' => -74.0])
        ->assertSessionHas(ActiveCity::SESSION_KEY, $near->id)
        ->assertSessionHas(ActiveCity::ZONE_SESSION_KEY, $nearZone->id);
});

test('detect rejects coordinates out of range', function () {
    $this->from(route('home'))
        ->post(route('location.detect'), ['lat' => 200, 'lng' => 0])
        ->assertSessionHasErrors('lat');
});

test('the detected city shows on the storefront', function () {
    $city = City::factory()->create(['name' => 'Shown City']);
    Zone::factory()->for($city)->around(40.7128, -74.0060)->create();

    $this->post(route('location.detect'), ['lat' => 40.7128, 'lng' => -74.0060]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('site.active_city.name', 'Shown City'));
});

test('a GPS-detected zone gates the catalog inside one city', function () {
    // Two zones in the SAME city: the city gate alone would still offer the
    // service, so this proves the detected *zone* is what narrows it.
    $city = City::factory()->create(['name' => 'Two Zone City']);
    $here = Zone::factory()->for($city)->around(40.7128, -74.0060)->create(['name' => 'Here Zone']);
    $there = Zone::factory()->for($city)->around(41.6, -72.4)->create(['name' => 'There Zone']);

    $category = Category::factory()->create();
    $service = Service::factory()->create(['category_id' => $category->id, 'name' => 'There Only Wash']);
    $service->zones()->attach($there);

    // City alone still offers it — the service serves a zone in this very city.
    $this->withSession([ActiveCity::SESSION_KEY => $city->id])
        ->get(route('catalog.show', [$category->slug, $service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', true));

    // Detect into the OTHER zone of the same city, and it drops away — the zone
    // gate is finer than the city gate.
    $this->withSession([ActiveCity::SESSION_KEY => $city->id, ActiveCity::ZONE_SESSION_KEY => $here->id])
        ->get(route('catalog.show', [$category->slug, $service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', false));
});

test('switching city clears a stale detected zone', function () {
    $city = City::factory()->create(['name' => 'Fresh City']);

    $this->withSession([ActiveCity::ZONE_SESSION_KEY => 9999])
        ->post(route('city.switch', $city))
        ->assertSessionMissing(ActiveCity::ZONE_SESSION_KEY);
});
