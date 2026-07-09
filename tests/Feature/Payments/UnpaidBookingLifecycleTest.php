<?php

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function lifecycleBooking(BookingStatus $status, PaymentMethod $method, ?string $createdAt = null): Booking
{
    $booking = Booking::factory()->create([
        'customer_id' => User::factory()->customer(),
        'status' => $status,
        'payment_method' => $method,
    ]);

    if ($createdAt !== null) {
        // created_at drives the payment window, so age the row directly.
        $booking->forceFill(['created_at' => $createdAt])->save();
    }

    return $booking->fresh();
}

test('an unpaid booking past the payment window expires', function () {
    app(SettingsRegistry::class)->set('booking.payment_timeout_minutes', 30);

    $stale = lifecycleBooking(BookingStatus::PendingPayment, PaymentMethod::Gateway, now()->subMinutes(45)->toDateTimeString());

    $this->artisan('bookings:expire-unpaid')->assertSuccessful();

    $stale->refresh();

    expect($stale->status)->toBe(BookingStatus::Expired)
        ->and($stale->statusHistory()->where('to_status', BookingStatus::Expired->value)->exists())->toBeTrue();
});

test('an unpaid booking inside the payment window survives', function () {
    app(SettingsRegistry::class)->set('booking.payment_timeout_minutes', 30);

    $fresh = lifecycleBooking(BookingStatus::PendingPayment, PaymentMethod::Gateway, now()->subMinutes(5)->toDateTimeString());

    $this->artisan('bookings:expire-unpaid')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('an already placed booking is never expired by the payment window', function () {
    app(SettingsRegistry::class)->set('booking.payment_timeout_minutes', 30);

    $placed = lifecycleBooking(BookingStatus::Placed, PaymentMethod::Cash, now()->subDays(2)->toDateTimeString());

    $this->artisan('bookings:expire-unpaid')->assertSuccessful();

    expect($placed->fresh()->status)->toBe(BookingStatus::Placed);
});

test('a gateway booking is never dispatched before the money lands', function () {
    Event::fake([BookingPlaced::class]);

    lifecycleBooking(BookingStatus::PendingPayment, PaymentMethod::Gateway);

    // Placement fired no BookingPlaced, so the M06 dispatch listener never ran.
    Event::assertNotDispatched(BookingPlaced::class);
});

test('completing a cash job records a captured cash payment exactly once', function () {
    $booking = lifecycleBooking(BookingStatus::InProgress, PaymentMethod::Cash);

    app(BookingStateMachine::class)->transition($booking, BookingStatus::Completed, BookingActor::Provider);

    $booking->refresh();

    expect($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->payments()->count())->toBe(1);

    $payment = $booking->payments()->first();

    expect($payment->gateway)->toBe(PaymentProvider::Cash)
        ->and($payment->status)->toBe(PaymentState::Captured)
        ->and($payment->amount)->toBe($booking->total)
        ->and($payment->captured_at)->not->toBeNull();
});

test('completing an already-paid online job does not double-charge', function () {
    $booking = lifecycleBooking(BookingStatus::InProgress, PaymentMethod::Gateway);
    $booking->forceFill(['payment_status' => PaymentStatus::Paid])->save();

    app(BookingStateMachine::class)->transition($booking, BookingStatus::Completed, BookingActor::Provider);

    expect($booking->fresh()->payments()->count())->toBe(0);
});
