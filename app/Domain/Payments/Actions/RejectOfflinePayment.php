<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OfflinePaymentRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The transfer never arrived, or the proof does not match (M22).
 *
 * The row fails with a reason; the booking is left exactly where it was —
 * `pending_payment` — so the customer can submit a corrected proof, pay by any
 * other method, or let `bookings:expire-unpaid` close it on the existing
 * schedule. Rejecting a payment is not a way to cancel a booking.
 */
class RejectOfflinePayment
{
    public function handle(Payment $payment, User $admin, string $reason): Payment
    {
        $rejected = DB::transaction(function () use ($payment, $admin, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->gateway !== PaymentProvider::Offline) {
                throw ValidationException::withMessages([
                    'payment' => __('Only an offline payment can be reviewed by hand.'),
                ]);
            }

            if ($locked->status !== PaymentState::Initiated) {
                throw ValidationException::withMessages([
                    'payment' => __('This payment has already been reviewed.'),
                ]);
            }

            $locked->forceFill([
                'status' => PaymentState::Failed,
                'failure_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });

        $customer = $rejected->booking?->customer;

        $customer?->notify(new OfflinePaymentRejectedNotification($rejected, $reason));

        return $rejected;
    }
}
