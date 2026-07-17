<?php

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Tests\Support\CheckoutFixtures;

/**
 * @return array{0: User, 1: array<string, mixed>}
 */
function contactCheckout(array $overrides = []): array
{
    [$customer, $address] = CheckoutFixtures::customer();
    $service = Service::factory()->create(['price' => 500]);

    test()->actingAs($customer);
    test()->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $payload = array_merge([
        'address_id' => $address->id,
        'scheduled_at' => CheckoutFixtures::slot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876512345',
    ], $overrides);

    return [$customer, $payload];
}

test('a booking cannot be placed without a contact phone', function () {
    [$customer, $payload] = contactCheckout();
    unset($payload['contact_phone']);

    $this->post(route('checkout.store'), $payload)
        ->assertSessionHasErrors('contact_phone');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('a phone that is not a phone is rejected', function (string $bad) {
    [$customer, $payload] = contactCheckout(['contact_phone' => $bad]);

    $this->post(route('checkout.store'), $payload)
        ->assertSessionHasErrors('contact_phone');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
})->with(['call me maybe', '12345', '++91 98765']);

test('both numbers are snapshotted on the booking and the alternate stays optional', function () {
    [$customer, $payload] = contactCheckout(['contact_phone_alt' => '+91 98765 00099']);

    $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->contact_phone)->toBe('9876512345')
        ->and($booking->contact_phone_alt)->toBe('+91 98765 00099');

    // And without one, the column simply stays null.
    [$again, $payloadTwo] = contactCheckout();
    $this->post(route('checkout.store'), $payloadTwo)->assertSessionHasNoErrors();

    expect(Booking::query()->where('customer_id', $again->id)->sole()->contact_phone_alt)->toBeNull();
});

test('booking backfills an empty profile phone', function () {
    [$customer, $payload] = contactCheckout();
    $customer->forceFill(['phone' => null])->save();

    $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

    expect($customer->fresh()?->phone)->toBe('9876512345');
});

test('a profile that already has a phone is left alone', function () {
    [$customer, $payload] = contactCheckout();
    $customer->forceFill(['phone' => '9000000001'])->save();

    $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

    expect($customer->fresh()?->phone)->toBe('9000000001');
});

test('backfill skips a number another account already owns', function () {
    User::factory()->create(['phone' => '9876512345']);

    [$customer, $payload] = contactCheckout();
    $customer->forceFill(['phone' => null])->save();

    // The unique column must not blow up the placement — the booking keeps
    // its own snapshot and the profile just stays empty.
    $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

    expect($customer->fresh()?->phone)->toBeNull()
        ->and(Booking::query()->where('customer_id', $customer->id)->sole()->contact_phone)->toBe('9876512345');
});

test('a booking from before the column falls back to the profile phone in the resource', function () {
    $customer = User::factory()->customer()->create(['phone' => '9111100001']);
    $booking = Booking::factory()->for($customer, 'customer')->create(['contact_phone' => null]);
    $booking->load('customer');

    $data = (new BookingResource($booking))->resolve();

    expect($data['contact_phone'])->toBe('9111100001');
});
