<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\User;
use Tests\Support\CheckoutFixtures;

test('a valid coupon applies and shows its discount at checkout', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(500);
    $coupon = Coupon::factory()->create(['code' => 'TESTFLAT', 'value' => '50.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'testflat'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->get(route('checkout.show'))->assertInertia(
        fn ($page) => $page
            ->component('checkout')
            ->where('coupon.code', $coupon->code)
            ->where('coupon.discount', '50.00')
            ->where('summary.discount', '50.00')
            // 500 − 50 = 450 taxable, 18% GST = 81 → 531.
            ->where('summary.tax', '81.00')
            ->where('summary.total', '531.00')
    );
});

test('a percent coupon is capped by max_discount', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(500);
    Coupon::factory()->percent(20.0, 60.0)->create(['code' => 'CAPPED']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    $this->post(route('checkout.coupon.store'), ['coupon' => 'CAPPED']);

    // 20% of 500 = 100, capped to 60.
    $this->get(route('checkout.show'))->assertInertia(
        fn ($page) => $page->where('coupon.discount', '60.00')
    );
});

test('a flat coupon larger than the order only discounts the order', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(100);
    Coupon::factory()->create(['code' => 'HUGE', 'value' => '5000.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    $this->post(route('checkout.coupon.store'), ['coupon' => 'HUGE']);

    $this->get(route('checkout.show'))->assertInertia(
        fn ($page) => $page
            ->where('coupon.discount', '100.00')
            ->where('summary.total', '0.00')
    );
});

test('an unknown code is rejected', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'NOPE'])
        ->assertSessionHasErrors('coupon');
});

test('inactive, not-yet-started and expired coupons are rejected', function (callable $factory) {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $factory();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'BLOCKED'])
        ->assertSessionHasErrors('coupon');
})->with([
    'inactive' => fn (): callable => fn () => Coupon::factory()->inactive()->create(['code' => 'BLOCKED']),
    'not started' => fn (): callable => fn () => Coupon::factory()->create(['code' => 'BLOCKED', 'starts_at' => now()->addDay()]),
    'expired' => fn (): callable => fn () => Coupon::factory()->expired()->create(['code' => 'BLOCKED']),
]);

test('a coupon below its minimum order is rejected', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(100);
    Coupon::factory()->create(['code' => 'MIN500', 'min_order' => '500.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'MIN500'])
        ->assertSessionHasErrors('coupon');
});

test('a first-order coupon refuses a customer with an earlier order', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    Booking::factory()->create(['customer_id' => $customer->id]);
    Coupon::factory()->create(['code' => 'FIRSTONLY', 'first_order_only' => true]);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'FIRSTONLY'])
        ->assertSessionHasErrors('coupon');
});

test('an expired-payment booking does not count as an earlier order', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    Booking::factory()->status(BookingStatus::Expired)->create(['customer_id' => $customer->id]);
    Coupon::factory()->create(['code' => 'FIRSTONLY', 'first_order_only' => true]);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'FIRSTONLY'])
        ->assertSessionHasNoErrors();
});

test('a stale session coupon is dropped with an explanation when checkout reloads', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $coupon = Coupon::factory()->create(['code' => 'SOONGONE', 'value' => '50.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    $this->post(route('checkout.coupon.store'), ['coupon' => 'SOONGONE']);

    $coupon->update(['is_active' => false]);

    $this->get(route('checkout.show'))->assertInertia(
        fn ($page) => $page
            ->where('coupon', null)
            ->where('summary.discount', '0.00')
            ->whereNot('coupon_error', null)
    );

    // Dropped for good — the next reload is clean.
    $this->get(route('checkout.show'))->assertInertia(fn ($page) => $page->where('coupon_error', null));
});

test('removing an applied coupon restores the full total', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service(500);
    Coupon::factory()->create(['code' => 'BYEBYE', 'value' => '50.00']);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
    $this->post(route('checkout.coupon.store'), ['coupon' => 'BYEBYE']);

    $this->delete(route('checkout.coupon.destroy'))->assertRedirect();

    $this->get(route('checkout.show'))->assertInertia(
        fn ($page) => $page->where('coupon', null)->where('summary.total', '590.00')
    );
});

test('guests cannot reach the coupon endpoint', function () {
    $this->post(route('checkout.coupon.store'), ['coupon' => 'ANY'])->assertRedirect(route('login'));
});

test('per-user limit counts only that user', function () {
    [$customer] = CheckoutFixtures::customer();
    $service = CheckoutFixtures::service();
    $coupon = Coupon::factory()->create(['code' => 'ONEEACH', 'per_user_limit' => 1]);

    // Another customer already used it — should not affect this one.
    $other = User::factory()->customer()->create();
    $otherBooking = Booking::factory()->create(['customer_id' => $other->id]);
    $coupon->usages()->create([
        'user_id' => $other->id,
        'booking_id' => $otherBooking->id,
        'discount_applied' => '50.00',
        'created_at' => now(),
    ]);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'ONEEACH'])
        ->assertSessionHasNoErrors();

    // Now spend this customer's one allowed use and try again.
    $ownBooking = Booking::factory()->create(['customer_id' => $customer->id]);
    $coupon->usages()->create([
        'user_id' => $customer->id,
        'booking_id' => $ownBooking->id,
        'discount_applied' => '50.00',
        'created_at' => now(),
    ]);

    $this->post(route('checkout.coupon.store'), ['coupon' => 'ONEEACH'])
        ->assertSessionHasErrors('coupon');
});
