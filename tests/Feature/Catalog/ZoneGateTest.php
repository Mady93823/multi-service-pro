<?php

use App\Models\Address;
use App\Models\Category;
use App\Models\City;
use App\Models\Service;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia;

/**
 * Zone gate (M03) and city gate (M25): the signed-in customer's default address
 * decides which services the storefront offers; a visitor with no address is
 * gated by the city they are browsing instead. Services without zone rows are
 * global. Coordinates/names avoid the seeded demo zones and catalog (the suite
 * seeds DatabaseSeeder before every test).
 *
 * @return array{0: Category, 1: User, 2: Service, 3: Service, 4: Service, 5: City, 6: City}
 */
function zoneGateWorld(): array
{
    $category = Category::factory()->create();

    $nycCity = City::factory()->create(['name' => 'Zonal New York']);
    $londonCity = City::factory()->create(['name' => 'Zonal London']);

    $newYork = Zone::factory()->for($nycCity)->around(40.7128, -74.0060)->create(['name' => 'NYC Test Zone']);
    $london = Zone::factory()->for($londonCity)->around(51.5074, -0.1278)->create(['name' => 'London Test Zone']);

    $inNewYork = Service::factory()->create(['category_id' => $category->id, 'name' => 'Zonal Wash East']);
    $inNewYork->zones()->attach($newYork);

    $inLondon = Service::factory()->create(['category_id' => $category->id, 'name' => 'Zonal Wash West']);
    $inLondon->zones()->attach($london);

    $everywhere = Service::factory()->create(['category_id' => $category->id, 'name' => 'Zonal Wash Global']);

    $customer = User::factory()->customer()->create();
    Address::factory()->for($customer)->default()->at(40.7128, -74.0060)->create(['zone_id' => $newYork->id]);

    return [$category, $customer, $inNewYork, $inLondon, $everywhere, $nycCity, $londonCity];
}

test('category listing hides services restricted to other zones', function () {
    [$category, $customer] = zoneGateWorld();

    $this->actingAs($customer)
        ->get(route('catalog.category', $category->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/category')
            ->has('services.data', 2))
        ->assertSee('Zonal Wash East')
        ->assertSee('Zonal Wash Global')
        ->assertDontSee('Zonal Wash West');
});

test('a guest sees the catalog of the city they are browsing', function () {
    [$category, , , , , $nycCity] = zoneGateWorld();

    $this->withSession(['city_id' => $nycCity->id])
        ->get(route('catalog.category', $category->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('services.data', 2))
        ->assertSee('Zonal Wash East')
        ->assertSee('Zonal Wash Global')
        ->assertDontSee('Zonal Wash West');
});

test('a customer with no default address is gated by the browsed city, not by nothing', function () {
    [$category, , , , , , $londonCity] = zoneGateWorld();

    $this->actingAs(User::factory()->customer()->create())
        ->withSession(['city_id' => $londonCity->id])
        ->get(route('catalog.category', $category->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('services.data', 2))
        ->assertSee('Zonal Wash West')
        ->assertDontSee('Zonal Wash East');
});

test('search results respect the zone gate', function () {
    [, $customer] = zoneGateWorld();

    $this->actingAs($customer)
        ->get(route('catalog.index', ['search' => 'Zonal Wash']))
        ->assertSee('Zonal Wash East')
        ->assertDontSee('Zonal Wash West');
});

test('service page reports availability at the default address', function () {
    [, $customer, $inNewYork, $inLondon, $everywhere] = zoneGateWorld();

    $this->actingAs($customer)
        ->get(route('catalog.show', [$inNewYork->category->slug, $inNewYork->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', true));

    $this->actingAs($customer)
        ->get(route('catalog.show', [$inLondon->category->slug, $inLondon->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', false));

    $this->actingAs($customer)
        ->get(route('catalog.show', [$everywhere->category->slug, $everywhere->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', true));
});

test('a guest is told a service is unavailable in the city they are browsing', function () {
    [, , , $inLondon, $everywhere, $nycCity] = zoneGateWorld();

    $this->withSession(['city_id' => $nycCity->id])
        ->get(route('catalog.show', [$inLondon->category->slug, $inLondon->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', false));

    // A service tied to no zone is on offer in every city.
    $this->withSession(['city_id' => $nycCity->id])
        ->get(route('catalog.show', [$everywhere->category->slug, $everywhere->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available_in_zone', true));
});
