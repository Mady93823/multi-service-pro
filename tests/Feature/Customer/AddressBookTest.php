<?php

use App\Models\Address;
use App\Models\User;
use App\Models\Zone;

// Coordinates deliberately far from the seeded demo zones (Bengaluru):
// the suite seeds DatabaseSeeder before every test.

function validAddressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'home',
        'line1' => '350 Fifth Avenue',
        'line2' => 'Midtown',
        'city' => 'New York',
        'postal_code' => '10118',
        'lat' => 40.7128,
        'lng' => -74.0060,
        'is_default' => false,
    ], $overrides);
}

test('first saved address resolves its zone and becomes the default', function () {
    $zone = Zone::factory()->around(40.7128, -74.0060)->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('addresses.store'), validAddressPayload())
        ->assertRedirect(route('addresses.index'));

    $address = $customer->addresses()->sole();

    expect($address->zone_id)->toBe($zone->id)
        ->and($address->is_default)->toBeTrue();
});

test('an address outside every zone saves with a null zone', function () {
    Zone::factory()->around(51.5074, -0.1278)->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->post(route('addresses.store'), validAddressPayload());

    expect($customer->addresses()->sole()->zone_id)->toBeNull();
});

test('marking an address as default demotes the previous default', function () {
    $customer = User::factory()->customer()->create();
    $first = Address::factory()->for($customer)->default()->create();

    $this->actingAs($customer)->post(route('addresses.store'), validAddressPayload(['is_default' => true]));

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($customer->addresses()->where('is_default', true)->count())->toBe(1);
});

test('moving the pin on update re-resolves the zone', function () {
    $zone = Zone::factory()->around(40.7128, -74.0060)->create();
    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->at(51.5074, -0.1278)->create();

    $this->actingAs($customer)
        ->put(route('addresses.update', $address), validAddressPayload())
        ->assertRedirect(route('addresses.index'));

    expect($address->refresh()->zone_id)->toBe($zone->id);
});

test('deleting the default address promotes the most recent remaining one', function () {
    $customer = User::factory()->customer()->create();
    $default = Address::factory()->for($customer)->default()->create();
    $other = Address::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->delete(route('addresses.destroy', $default))
        ->assertRedirect(route('addresses.index'));

    expect($other->refresh()->is_default)->toBeTrue();
});

test('the default switch endpoint moves the default flag', function () {
    $customer = User::factory()->customer()->create();
    $default = Address::factory()->for($customer)->default()->create();
    $other = Address::factory()->for($customer)->create();

    $this->actingAs($customer)->put(route('addresses.default', $other));

    expect($other->refresh()->is_default)->toBeTrue()
        ->and($default->refresh()->is_default)->toBeFalse();
});

test('customers cannot touch another user\'s address', function () {
    $stranger = Address::factory()->default()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->put(route('addresses.update', $stranger), validAddressPayload())
        ->assertForbidden();

    $this->actingAs($customer)
        ->delete(route('addresses.destroy', $stranger))
        ->assertForbidden();

    $this->actingAs($customer)
        ->put(route('addresses.default', $stranger))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $this->get(route('addresses.index'))->assertRedirect(route('login'));
});

test('a pin outside valid coordinates is rejected', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->post(route('addresses.store'), validAddressPayload(['lat' => 95, 'lng' => 200]))
        ->assertSessionHasErrors(['lat', 'lng']);
});
