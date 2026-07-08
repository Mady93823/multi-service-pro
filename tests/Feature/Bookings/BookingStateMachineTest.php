<?php

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Exceptions\IllegalTransition;
use App\Domain\Bookings\Exceptions\InvalidJobOtp;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;

function machine(): BookingStateMachine
{
    return app(BookingStateMachine::class);
}

test('the happy path walks every status and logs each transition', function () {
    $booking = Booking::factory()->create();
    $admin = User::factory()->admin()->create();
    $provider = User::factory()->provider()->create();

    machine()->transition($booking, BookingStatus::Searching, BookingActor::Admin, $admin);

    $booking->provider_id = $provider->id;

    foreach ([
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
        BookingStatus::Completed,
    ] as $status) {
        machine()->transition($booking, $status, BookingActor::Admin, $admin);
    }

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->completed_at)->not->toBeNull()
        ->and($booking->statusHistory()->count())->toBe(7)
        ->and($booking->statusHistory()->orderBy('id')->pluck('to_status')->all())->toBe([
            'searching', 'assigned', 'accepted', 'en_route', 'arrived', 'in_progress', 'completed',
        ]);
});

test('illegal transitions throw and change nothing', function () {
    $booking = Booking::factory()->create();

    expect(fn () => machine()->transition($booking, BookingStatus::Completed, BookingActor::Admin))
        ->toThrow(IllegalTransition::class);

    expect($booking->refresh()->status)->toBe(BookingStatus::Placed)
        ->and($booking->statusHistory()->count())->toBe(0);
});

test('terminal states allow no further transitions', function () {
    $booking = Booking::factory()->status(BookingStatus::Completed)->create();

    expect(fn () => machine()->transition($booking, BookingStatus::Searching, BookingActor::Admin))
        ->toThrow(IllegalTransition::class);
});

test('a booking cannot be assigned without a provider', function () {
    $booking = Booking::factory()->status(BookingStatus::Searching)->create();

    expect(fn () => machine()->transition($booking, BookingStatus::Assigned, BookingActor::Admin))
        ->toThrow(IllegalTransition::class);
});

test('the provider must present the correct job otp to start the job', function () {
    $provider = User::factory()->provider()->create();
    $booking = Booking::factory()->status(BookingStatus::Arrived)->withProvider($provider)->create();

    expect(fn () => machine()->transition($booking, BookingStatus::InProgress, BookingActor::Provider, $provider, null, '9999'))
        ->toThrow(InvalidJobOtp::class);

    machine()->transition($booking, BookingStatus::InProgress, BookingActor::Provider, $provider, null, '1234');

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);
});

test('the otp gate is skipped when the setting is off', function () {
    app(SettingsRegistry::class)->set('booking.job_otp_required', false);

    $provider = User::factory()->provider()->create();
    $booking = Booking::factory()->status(BookingStatus::Arrived)->withProvider($provider)->create();

    machine()->transition($booking, BookingStatus::InProgress, BookingActor::Provider, $provider, null, 'wrong');

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);
});

test('admins bypass the job otp', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->status(BookingStatus::Arrived)->withProvider()->create();

    machine()->transition($booking, BookingStatus::InProgress, BookingActor::Admin, $admin);

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);
});

test('cancellations record who cancelled and why', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->for($customer, 'customer')->create();

    machine()->transition($booking, BookingStatus::CancelledCustomer, BookingActor::Customer, $customer, 'Changed my mind');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CancelledCustomer)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->cancelled_by)->toBe($customer->id)
        ->and($booking->cancel_reason)->toBe('Changed my mind');
});

test('returning to searching releases the provider', function () {
    $booking = Booking::factory()->status(BookingStatus::Assigned)->withProvider()->create();

    machine()->transition($booking, BookingStatus::Searching, BookingActor::Admin);

    expect($booking->refresh()->provider_id)->toBeNull();
});
