<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Enums\PaymentState;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Money confirmed (callback signature or webhook — both routes land here).
 * Idempotent: the payment row is locked and a second confirmation of a
 * captured payment is a no-op, so webhook replays cannot double-fire
 * dispatch or double-mark anything (M08 done-when).
 */
class ConfirmPayment
{
    public function __construct(private readonly BookingStateMachine $machine) {}

    /**
     * @param  array<string, mixed>  $payload  extra gateway evidence to keep
     */
    public function handle(Payment $payment, array $payload = []): Booking
    {
        $placed = false;

        $booking = DB::transaction(function () use ($payment, $payload, &$placed): Booking {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentState::Captured) {
                return $booking; // replay — already settled
            }

            $locked->forceFill([
                'status' => PaymentState::Captured,
                'captured_at' => now(),
                'payload' => array_merge($locked->payload ?? [], $payload === [] ? [] : ['confirmation' => $payload]),
            ])->save();

            $booking->payment_status = PaymentStatus::Paid;

            // The customer paid for a gateway booking — reflect the method
            // actually used (they may have started with the wallet option).
            if ($locked->gateway->isOnlineGateway()) {
                $booking->payment_method = PaymentMethod::Gateway;
            }

            $booking->save();

            if ($booking->status === BookingStatus::PendingPayment) {
                $this->machine->transition($booking, BookingStatus::Placed, BookingActor::System);
                $placed = true;
            } elseif ($booking->status->isTerminal()) {
                // Paid after the payment window closed (booking expired or
                // cancelled): keep the captured money on record and flag it —
                // support refunds it from the admin booking screen.
                Log::warning('Payment captured for a terminal booking — needs a refund.', [
                    'booking_id' => $booking->id,
                    'payment_id' => $locked->id,
                    'status' => $booking->status->value,
                ]);
            }

            return $booking;
        });

        // Outside the transaction: dispatch (M06) reads committed state.
        if ($placed) {
            BookingPlaced::dispatch($booking);
        }

        return $booking;
    }
}
