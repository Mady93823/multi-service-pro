<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

function admin(): User
{
    return User::factory()->admin()->create();
}

test('non-admins are blocked from the booking panel', function () {
    $booking = Booking::factory()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('admin.bookings.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.bookings.show', $booking))->assertForbidden();
    $this->actingAs($customer)
        ->post(route('admin.bookings.transition', $booking), ['to' => 'searching'])
        ->assertForbidden();
});

test('the booking list filters by status', function () {
    Booking::factory()->status(BookingStatus::Completed)->create(['code' => 'BK-2026-777777']);

    $this->actingAs(admin())
        ->get(route('admin.bookings.index', ['status' => 'completed']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/bookings/index')
            ->where('filters.status', 'completed'));
});

test('an admin can advance a booking through the state machine', function () {
    $booking = Booking::factory()->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), ['to' => 'searching'])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Searching)
        ->and($booking->statusHistory()->sole()->actor_type)->toBe('admin');
});

test('assigning requires picking a provider', function () {
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), ['to' => 'assigned'])
        ->assertSessionHasErrors('provider_id');
});

test('an admin can assign a provider manually', function () {
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();
    $provider = User::factory()->provider()->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), [
            'to' => 'assigned',
            'provider_id' => $provider->id,
        ])
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->provider_id)->toBe($provider->id)
        ->and($booking->status)->toBe(BookingStatus::Assigned);
});

test('a non-provider user cannot be assigned', function () {
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), [
            'to' => 'assigned',
            'provider_id' => $customer->id,
        ])
        ->assertSessionHasErrors('provider_id');
});

test('admin cancellation requires a reason and skips the fee', function () {
    $booking = Booking::factory()->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), ['to' => 'cancelled_admin'])
        ->assertSessionHasErrors('note');

    $this->post(route('admin.bookings.transition', $booking), [
        'to' => 'cancelled_admin',
        'note' => 'Provider unavailable in the area.',
    ])->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CancelledAdmin)
        ->and((string) $booking->cancellation_fee)->toBe('0.00')
        ->and($booking->cancel_reason)->toBe('Provider unavailable in the area.');
});

test('illegal transitions surface as validation errors', function () {
    $booking = Booking::factory()->status(BookingStatus::Completed)->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), ['to' => 'searching'])
        ->assertSessionHasErrors('to');
});

test('an admin can start the job without the customer otp', function () {
    $booking = Booking::factory()->status(BookingStatus::Arrived)->withProvider()->create();

    $this->actingAs(admin())
        ->post(route('admin.bookings.transition', $booking), ['to' => 'in_progress'])
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);
});

test('the admin detail page lists only legal next steps', function () {
    $booking = Booking::factory()->create(); // placed

    $this->actingAs(admin())
        ->get(route('admin.bookings.show', $booking))
        ->assertInertia(fn ($page) => $page
            ->component('admin/bookings/show')
            ->where('allowed_transitions', ['searching', 'cancelled_admin']));
});
