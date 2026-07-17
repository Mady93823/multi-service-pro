<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Coupon;
use Tests\Support\CheckoutFixtures;

function placeWithCoupon(string $method = 'cash'): array
{
    [$customer, $address] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(500);
    $coupon = Coupon::factory()->create(['code' => 'REDEEM50', 'value' => '50.00']);

    test()->actingAs($customer);
    test()->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    test()->post(route('checkout.coupon.store'), ['coupon' => 'REDEEM50']);

    $response = test()->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => CheckoutFixtures::slot(),
        'payment_method' => $method,
        'contact_phone' => '9876500002',
    ]);

    return [$customer, $coupon, $response];
}

test('placing a booking snapshots the coupon and writes the usage row', function () {
    [$customer, $coupon] = placeWithCoupon();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    // 500 − 50 = 450 taxable, 18% GST = 81 → 531.
    expect($booking->coupon_id)->toBe($coupon->id)
        ->and((string) $booking->discount)->toBe('50.00')
        ->and((string) $booking->tax)->toBe('81.00')
        ->and((string) $booking->total)->toBe('531.00');

    $usage = $coupon->usages()->sole();
    expect($usage->booking_id)->toBe($booking->id)
        ->and($usage->user_id)->toBe($customer->id)
        ->and((string) $usage->discount_applied)->toBe('50.00');

    // Session coupon is spent with the cart.
    expect($this->app['session.store']->get('cart.coupon'))->toBeNull();
});

test('a coupon that dies between apply and placement blocks the booking and is dropped', function () {
    [$customer, $address] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $coupon = Coupon::factory()->create(['code' => 'DYING', 'value' => '50.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    $this->post(route('checkout.coupon.store'), ['coupon' => 'DYING']);

    $coupon->update(['is_active' => false]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => CheckoutFixtures::slot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500002',
    ])->assertSessionHasErrors('coupon');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse()
        ->and($this->app['session.store']->get('cart.coupon'))->toBeNull();
});

test('a fully-redeemed usage limit refuses the next customer', function () {
    [, $coupon] = placeWithCoupon();
    $coupon->update(['usage_limit' => 1]);

    [$other] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();

    $this->actingAs($other);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'REDEEM50'])
        ->assertSessionHasErrors('coupon');
});

test('a cancelled booking keeps its usage spent', function () {
    [$customer, $coupon] = placeWithCoupon();
    $coupon->update(['usage_limit' => 1]);

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $this->post(route('bookings.cancel', $booking))->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::CancelledCustomer);

    // UC parity (ADR D18): cancelling does not hand the redemption back.
    [$other] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $this->actingAs($other);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'REDEEM50'])
        ->assertSessionHasErrors('coupon');
});

test('a booking that expires unpaid releases its usage back to the pool', function () {
    // Wallet with zero balance: the booking lands pending_payment and waits.
    [$customer, $coupon] = placeWithCoupon('wallet');
    $coupon->update(['usage_limit' => 1]);

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    expect($booking->status)->toBe(BookingStatus::PendingPayment);

    $this->travel(31)->minutes();
    $this->artisan('bookings:expire-unpaid')->assertSuccessful();
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired);

    // Money never moved, so the last slot is free again (ADR D18).
    [$other] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $this->actingAs($other);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'REDEEM50'])
        ->assertSessionHasNoErrors();
});
