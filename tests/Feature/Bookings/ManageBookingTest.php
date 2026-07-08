<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\SlotGenerator;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;

function openSlot(): string
{
    $days = app(SlotGenerator::class)->days();

    return $days[0]['slots'][0]['value'];
}

test('cancelling early is free', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')
        ->scheduledAt(CarbonImmutable::now()->addHours(48))
        ->create();

    $this->actingAs($customer)
        ->post(route('bookings.cancel', $booking), ['reason' => 'Plans changed'])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CancelledCustomer)
        ->and((string) $booking->cancellation_fee)->toBe('0.00')
        ->and($booking->cancel_reason)->toBe('Plans changed');
});

test('cancelling close to the visit snapshots the fee', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')
        ->scheduledAt(CarbonImmutable::now()->addHour())
        ->create();

    $this->actingAs($customer)->post(route('bookings.cancel', $booking));

    // 10% of the 590.00 factory total.
    expect((string) $booking->refresh()->cancellation_fee)->toBe('59.00');
});

test('a completed booking cannot be cancelled', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')->status(BookingStatus::Completed)->create();

    $this->actingAs($customer)
        ->post(route('bookings.cancel', $booking))
        ->assertSessionHasErrors('status');
});

test('strangers cannot cancel someone else\'s booking', function () {
    $booking = Booking::factory()->create();
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->post(route('bookings.cancel', $booking))
        ->assertForbidden();
});

test('rescheduling a placed booking moves the slot and logs it', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')
        ->scheduledAt(CarbonImmutable::now()->addDays(2))
        ->create();

    $slot = openSlot();

    $this->actingAs($customer)
        ->post(route('bookings.reschedule', $booking), ['scheduled_at' => $slot])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->scheduled_at->toIso8601String())
        ->toBe(CarbonImmutable::parse($slot)->utc()->toIso8601String())
        ->and($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->statusHistory()->count())->toBe(1); // audit note row
});

test('rescheduling an assigned booking releases the provider', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')
        ->status(BookingStatus::Assigned)
        ->withProvider()
        ->scheduledAt(CarbonImmutable::now()->addDays(2))
        ->create();

    $this->actingAs($customer)->post(route('bookings.reschedule', $booking), ['scheduled_at' => openSlot()]);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Searching)
        ->and($booking->provider_id)->toBeNull();
});

test('rescheduling too close to the visit is refused', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')
        ->scheduledAt(CarbonImmutable::now()->addHour())
        ->create();

    $this->actingAs($customer)
        ->post(route('bookings.reschedule', $booking), ['scheduled_at' => openSlot()])
        ->assertSessionHasErrors('scheduled_at');
});

test('book again refills the cart from the snapshots', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')->status(BookingStatus::Completed)->create();

    $service = Service::factory()->create();
    $booking->items()->create([
        'service_id' => $service->id,
        'name_snapshot' => $service->name,
        'price_snapshot' => $service->price,
        'qty' => 2,
        'addons_snapshot' => [],
    ]);

    $this->actingAs($customer)
        ->post(route('bookings.rebook', $booking))
        ->assertRedirect(route('cart.show'));

    $lines = $this->app['session.store']->get('cart.items');

    expect($lines)->toHaveCount(1)
        ->and(array_values($lines)[0]['service_id'])->toBe($service->id)
        ->and(array_values($lines)[0]['qty'])->toBe(2);
});

test('book again skips retired services', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')->status(BookingStatus::Completed)->create();

    $service = Service::factory()->inactive()->create();
    $booking->items()->create([
        'service_id' => $service->id,
        'name_snapshot' => $service->name,
        'price_snapshot' => $service->price,
        'qty' => 1,
        'addons_snapshot' => [],
    ]);

    $this->actingAs($customer)->post(route('bookings.rebook', $booking));

    expect($this->app['session.store']->get('cart.items', []))->toBe([]);
});

test('a customer can favorite and unfavorite a provider', function () {
    $customer = User::factory()->customer()->create();
    $provider = User::factory()->provider()->create();

    $this->actingAs($customer)->post(route('providers.favorite', $provider));
    expect($customer->refresh()->hasFavorited($provider))->toBeTrue();

    $this->post(route('providers.favorite', $provider));
    expect($customer->refresh()->hasFavorited($provider))->toBeFalse();
});

test('only providers can be favorited', function () {
    $customer = User::factory()->customer()->create();
    $notProvider = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('providers.favorite', $notProvider))
        ->assertNotFound();
});

test('the booking detail shows the otp to its customer only', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')->create();

    $this->actingAs($customer)
        ->get(route('bookings.show', $booking))
        ->assertInertia(fn ($page) => $page
            ->component('customer/bookings/show')
            ->where('booking.job_otp_code', '1234'));
});
