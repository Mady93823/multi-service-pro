<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Payments\WalletService;
use App\Models\Address;
use App\Models\Booking;
use App\Models\City;
use App\Models\Service;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\ZoneFactory;
use Illuminate\Testing\TestResponse;

/**
 * D43 — cash (pay-after-service) is offered per geography: the zone AND its
 * city must both allow it, while online methods are never gated by where the
 * address is. The checkout picker filtering cash out is presentation; the
 * request rule is the enforcement these tests pin.
 */

/**
 * @return array{0: User, 1: Address}
 */
function cashZoneCustomer(Zone $zone): array
{
    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->default()->create(['zone_id' => $zone->id]);

    return [$customer, $address];
}

/** The service must serve the zone, or placement blocks on the zone gate (M03). */
function cashZoneCart(User $customer, Zone ...$zones): void
{
    $service = Service::factory()->create(['price' => 500]);

    foreach ($zones as $zone) {
        $zone->services()->attach($service);
    }

    test()->actingAs($customer);
    test()->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
}

function cashZonePlace(Address $address, string $method): TestResponse
{
    return test()->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => app(SlotGenerator::class)->days()[0]['slots'][0]['value'],
        'payment_method' => $method,
        'contact_phone' => '9876500005',
    ]);
}

test('checkout marks which addresses take cash', function () {
    $cashZone = Zone::factory()->create();
    $onlineOnly = Zone::factory()->cashDisabled()->create();

    [$customer, $cashAddress] = cashZoneCustomer($cashZone);
    $onlineAddress = Address::factory()->for($customer)->create(['zone_id' => $onlineOnly->id]);

    cashZoneCart($customer, $cashZone, $onlineOnly);

    // Default address rides first (orderByDesc is_default), so the order is fixed.
    test()->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('addresses.0.address.id', $cashAddress->id)
            ->where('addresses.0.cash_allowed', true)
            ->where('addresses.1.address.id', $onlineAddress->id)
            ->where('addresses.1.cash_allowed', false));
});

test('cash cannot be posted for an address in an online-only zone', function () {
    $zone = Zone::factory()->cashDisabled()->create();
    [$customer, $address] = cashZoneCustomer($zone);
    cashZoneCart($customer, $zone);

    cashZonePlace($address, 'cash')->assertSessionHasErrors('payment_method');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

test('cash places normally in a zone that allows it', function () {
    $zone = Zone::factory()->create();
    [$customer, $address] = cashZoneCustomer($zone);
    cashZoneCart($customer, $zone);

    cashZonePlace($address, 'cash')->assertRedirect();

    expect(Booking::query()->where('customer_id', $customer->id)->sole()->status)
        ->toBe(BookingStatus::Placed);
});

test('the city switch closes cash for every zone in it', function () {
    // The zone's own flag stays on — the city veto must win regardless.
    $city = City::factory()->cashDisabled()->create();
    $zone = Zone::factory()->for($city)->create();

    [$customer, $address] = cashZoneCustomer($zone);
    cashZoneCart($customer, $zone);

    cashZonePlace($address, 'cash')->assertSessionHasErrors('payment_method');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

test('online methods are untouched in an online-only zone', function () {
    $zone = Zone::factory()->cashDisabled()->create();
    [$customer, $address] = cashZoneCustomer($zone);
    app(WalletService::class)->credit($customer, '1000.00', 'topup');
    cashZoneCart($customer, $zone);

    cashZonePlace($address, 'wallet')->assertRedirect();

    expect(Booking::query()->where('customer_id', $customer->id)->sole()->status)
        ->toBe(BookingStatus::Placed);
});

test('an admin can flip the zone cash switch', function () {
    $zone = Zone::factory()->create();

    test()->actingAs(User::factory()->admin()->create())
        ->put(route('admin.zones.update', $zone), [
            'city_id' => $zone->city_id,
            'name' => $zone->name,
            'geojson' => ZoneFactory::squareAround(12.9716, 77.5946),
            'is_active' => true,
            'cash_enabled' => false,
        ])
        ->assertRedirect();

    expect($zone->fresh()?->cash_enabled)->toBeFalse();
});

test('an admin can flip the city cash switch', function () {
    $city = City::factory()->create();

    test()->actingAs(User::factory()->admin()->create())
        ->put(route('admin.cities.update', $city), [
            'name' => $city->name,
            'timezone' => $city->timezone,
            'center_lat' => $city->center_lat,
            'center_lng' => $city->center_lng,
            'is_active' => true,
            'cash_enabled' => false,
        ])
        ->assertRedirect();

    expect($city->fresh()?->cash_enabled)->toBeFalse();
});
