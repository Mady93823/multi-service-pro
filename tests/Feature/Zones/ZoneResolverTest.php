<?php

use App\Domain\Zones\ZoneResolver;
use App\Models\Zone;

// Coordinates deliberately far from the seeded demo zones (Bengaluru):
// the suite seeds DatabaseSeeder before every test.

test('resolves the zone containing a point', function () {
    Zone::factory()->around(51.5074, -0.1278)->create(['name' => 'London Test Zone']);
    $newYork = Zone::factory()->around(40.7128, -74.0060)->create(['name' => 'NYC Test Zone']);

    $resolved = app(ZoneResolver::class)->resolve(40.7128, -74.0060);

    expect($resolved?->id)->toBe($newYork->id);
});

test('inactive zones are ignored', function () {
    Zone::factory()->around(40.7128, -74.0060)->inactive()->create();

    expect(app(ZoneResolver::class)->resolve(40.7128, -74.0060))->toBeNull();
});

test('returns null when no zone covers the point', function () {
    Zone::factory()->around(40.7128, -74.0060)->create();

    expect(app(ZoneResolver::class)->resolve(0.0, 0.0))->toBeNull();
});
