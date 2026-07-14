<?php

use App\Models\City;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

// The seeder ships two demo cities (Bengaluru, Mysuru) — every count here is
// scoped, never a bare City::count().

test('admin can create a city and the slug is derived when left blank', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cities.store'), [
            'name' => 'New Delhi',
            'timezone' => 'Asia/Kolkata',
            'center_lat' => 28.6139,
            'center_lng' => 77.2090,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.cities.index'));

    $city = City::query()->where('name', 'New Delhi')->sole();

    expect($city->slug)->toBe('new-delhi')
        ->and($city->is_active)->toBeTrue();
});

test('a second city with the same name gets its own slug', function () {
    $admin = User::factory()->admin()->create();

    $payload = [
        'name' => 'Springfield',
        'timezone' => 'Asia/Kolkata',
        'center_lat' => 12.0,
        'center_lng' => 77.0,
    ];

    $this->actingAs($admin)->post(route('admin.cities.store'), $payload);
    $this->actingAs($admin)->post(route('admin.cities.store'), $payload);

    expect(City::query()->where('name', 'Springfield')->pluck('slug')->all())
        ->toBe(['springfield', 'springfield-2']);
});

test('an unknown timezone is refused', function () {
    // The city's timezone decides what "9:00 AM" means there (M25) — a typo
    // would silently move every slot the city offers.
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cities.store'), [
            'name' => 'Nowhere',
            'timezone' => 'Mars/Olympus',
            'center_lat' => 12.0,
            'center_lng' => 77.0,
        ])
        ->assertSessionHasErrors('timezone');
});

test('a city with zones cannot be deleted', function () {
    $city = City::factory()->create();
    Zone::factory()->for($city)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.cities.destroy', $city))
        ->assertSessionHasErrors('city');

    expect(City::query()->whereKey($city->id)->exists())->toBeTrue();
});

test('an empty city can be deleted', function () {
    $city = City::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.cities.destroy', $city))
        ->assertRedirect(route('admin.cities.index'));

    expect(City::query()->whereKey($city->id)->exists())->toBeFalse();
});

test('the city list carries its zone and booking counts', function () {
    $city = City::factory()->create(['name' => 'Counted City']);
    Zone::factory()->count(2)->for($city)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cities.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/cities/index')
            ->where('cities', fn (Collection $cities): bool => $cities
                ->firstWhere('name', 'Counted City')['zones_count'] === 2));
});

test('non-admins cannot manage cities', function () {
    $city = City::factory()->create();

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.cities.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->provider()->create())
        ->delete(route('admin.cities.destroy', $city))
        ->assertForbidden();
});
