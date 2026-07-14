<?php

use App\Models\Address;
use App\Models\City;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\ZoneFactory;

// Coordinates deliberately far from the seeded demo zones (Bengaluru):
// the suite seeds DatabaseSeeder before every test.

test('admin can create a zone with a drawn polygon', function () {
    $city = City::factory()->create(['name' => 'Zone Test City']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.zones.store'), [
            'city_id' => $city->id,
            'name' => 'NYC South',
            'geojson' => ZoneFactory::squareAround(40.7128, -74.0060),
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.zones.index'));

    $zone = Zone::query()->where('name', 'NYC South')->sole();

    expect($zone->geojson['type'])->toBe('Polygon')
        ->and($zone->city_id)->toBe($city->id)
        ->and($zone->contains(40.7128, -74.0060))->toBeTrue();
});

test('a zone cannot be created without an existing city', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.zones.store'), [
            'name' => 'Orphan',
            'geojson' => ZoneFactory::squareAround(40.7128, -74.0060),
        ])
        ->assertSessionHasErrors('city_id');

    $this->actingAs($admin)
        ->post(route('admin.zones.store'), [
            'city_id' => 999999,
            'name' => 'Orphan',
            'geojson' => ZoneFactory::squareAround(40.7128, -74.0060),
        ])
        ->assertSessionHasErrors('city_id');
});

test('an unclosed or missing polygon is rejected', function () {
    $admin = User::factory()->admin()->create();
    $city = City::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.zones.store'), ['city_id' => $city->id, 'name' => 'Bad', 'geojson' => null])
        ->assertSessionHasErrors('geojson');

    $unclosed = [
        'type' => 'Polygon',
        'coordinates' => [[[-74.1, 40.6], [-73.9, 40.6], [-73.9, 40.8], [-74.1, 40.8]]],
    ];

    $this->actingAs($admin)
        ->post(route('admin.zones.store'), ['city_id' => $city->id, 'name' => 'Bad', 'geojson' => $unclosed])
        ->assertSessionHasErrors('geojson');
});

test('non-admins cannot manage zones', function () {
    $zone = Zone::factory()->create();

    $this->actingAs(User::factory()->customer()->create())
        ->post(route('admin.zones.store'), [
            'city_id' => $zone->city_id,
            'name' => 'Nope',
            'geojson' => ZoneFactory::squareAround(1, 1),
        ])
        ->assertForbidden();

    $this->actingAs(User::factory()->provider()->create())
        ->delete(route('admin.zones.destroy', $zone))
        ->assertForbidden();
});

test('creating a zone assigns addresses that were outside every zone', function () {
    $address = Address::factory()->at(40.7128, -74.0060)->create();

    expect($address->zone_id)->toBeNull();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.zones.store'), [
            'city_id' => City::factory()->create()->id,
            'name' => 'NYC Central',
            'geojson' => ZoneFactory::squareAround(40.7128, -74.0060),
            'is_active' => true,
        ]);

    expect($address->refresh()->zone_id)->toBe(Zone::query()->where('name', 'NYC Central')->sole()->id);
});

test('shrinking a zone drops addresses that fell outside it', function () {
    $zone = Zone::factory()->around(40.7128, -74.0060)->create();
    $address = Address::factory()->at(40.7128, -74.0060)->create(['zone_id' => $zone->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.zones.update', $zone), [
            'city_id' => $zone->city_id,
            'name' => $zone->name,
            'geojson' => ZoneFactory::squareAround(51.5074, -0.1278), // moved to London
            'is_active' => true,
        ]);

    expect($address->refresh()->zone_id)->toBeNull();
});

test('deleting a zone re-resolves its addresses against remaining zones', function () {
    $city = City::factory()->create();
    $inner = Zone::factory()->for($city)->around(40.7128, -74.0060, 0.02)->create(['name' => 'NYC Inner']);
    $outer = Zone::factory()->for($city)->around(40.7128, -74.0060, 0.20)->create(['name' => 'NYC Outer']);
    $address = Address::factory()->at(40.7128, -74.0060)->create(['zone_id' => $inner->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.zones.destroy', $inner))
        ->assertRedirect(route('admin.zones.index'));

    expect(Zone::query()->whereKey($inner->id)->exists())->toBeFalse()
        ->and($address->refresh()->zone_id)->toBe($outer->id);
});
