<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\Events\BookingRefunded;
use App\Domain\Payments\WalletService;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refunds land in the customer's wallet (M08 v1 — instant, no gateway API
 * round-trip; gateway-side refunds are a later enhancement behind the same
 * seam). Amount may be partial (cancellation fee withheld).
 */
class RefundBookingToWallet
{
    public function __construct(private readonly WalletService $wallet) {}

    public function handle(Booking $booking, ?float $amount = null, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $amount, $note): Booking {
            /** @var Booking $booking */
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'refund' => __('Only a paid booking can be refunded.'),
                ]);
            }

            $captured = (float) $booking->payments()
                ->where('status', PaymentState::Captured->value)
                ->sum('amount');

            $amount ??= $captured;

            if ($amount <= 0 || $amount > $captured) {
                throw ValidationException::withMessages([
                    'refund' => __('The refund amount must be between zero and the amount paid.'),
                ]);
            }

            $customer = $booking->customer;

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'refund' => __('The customer account for this booking no longer exists.'),
                ]);
            }

            $this->wallet->credit(
                $customer,
                $amount,
                'refund',
                Booking::class,
                $booking->id,
                $note ?? $booking->code,
            );

            $booking->payments()
                ->where('status', PaymentState::Captured->value)
                ->get()
                ->each(fn (Payment $payment) => $payment->forceFill(['status' => PaymentState::Refunded])->save());

            $booking->payment_status = $amount < $captured
                ? PaymentStatus::PartialRefund
                : PaymentStatus::Refunded;
            $booking->save();

            BookingRefunded::dispatch($booking, $amount);

            return $booking;
        });
    }
}
