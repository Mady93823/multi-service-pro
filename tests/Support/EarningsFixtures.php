<?php

namespace Tests\Support;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;

/**
 * Shared M09 fixtures. A class rather than Pest global functions, because
 * `--parallel` loads test files independently and a helper declared in one of
 * them is not guaranteed to exist in another.
 *
 * Booking money always follows BookingFactory: subtotal 500, tax 90, total 590.
 * At 20% commission that is 100 to the platform, leaving 400 on an online job
 * and −190 on a cash one (the provider pocketed all 590 at the door).
 */
class EarningsFixtures
{
    public static function booking(
        PaymentMethod $method = PaymentMethod::Wallet,
        ?Service $service = null,
        ?User $provider = null,
    ): Booking {
        $service ??= Service::factory()->create(['price' => 500]);
        $provider ??= User::factory()->provider()->create();

        $booking = Booking::factory()->status(BookingStatus::InProgress)->create([
            'provider_id' => $provider->id,
            'payment_method' => $method,
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => '500.00',
            'qty' => 1,
            'addons_snapshot' => [],
        ]);

        return $booking;
    }

    /** Admin actor: bypasses the job-start OTP guard the provider would face. */
    public static function complete(Booking $booking): Booking
    {
        return app(BookingStateMachine::class)->transition(
            $booking,
            BookingStatus::Completed,
            BookingActor::Admin,
            User::factory()->admin()->create(),
        );
    }

    /** A captured gateway payment, so RefundBookingToWallet has something to give back. */
    public static function markPaid(Booking $booking): Booking
    {
        $booking->payments()->create([
            'gateway' => PaymentProvider::Razorpay,
            'gateway_ref' => 'order_'.$booking->id,
            'amount' => $booking->total,
            'currency' => 'INR',
            'status' => PaymentState::Captured,
            'captured_at' => now(),
        ]);

        $booking->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        return $booking;
    }
}
