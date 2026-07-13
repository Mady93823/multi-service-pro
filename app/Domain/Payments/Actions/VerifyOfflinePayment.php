<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * An admin has seen the money land (M22, D27).
 *
 * The whole point of this action is what it does NOT do: it does not mark the
 * booking paid, does not place it and does not touch the earnings ledger. It
 * guards the transition (this is an offline row, and it is still open) and then
 * hands over to `ConfirmPayment` — the same row-locked, idempotent action every
 * gateway webhook calls. There is one money path, so a double-click cannot
 * double-settle and an offline booking's history is indistinguishable from a
 * Razorpay one.
 */
class VerifyOfflinePayment
{
    public function __construct(private readonly ConfirmPayment $confirm) {}

    public function handle(Payment $payment, User $admin): Booking
    {
        if ($payment->gateway !== PaymentProvider::Offline) {
            throw ValidationException::withMessages([
                'payment' => __('Only an offline payment can be verified by hand.'),
            ]);
        }

        if ($payment->status !== PaymentState::Initiated) {
            throw ValidationException::withMessages([
                'payment' => __('This payment has already been reviewed.'),
            ]);
        }

        // ConfirmPayment re-locks the row and no-ops on a captured one, so a
        // racing second verification settles nothing twice.
        $booking = $this->confirm->handle($payment, [
            'verified_by' => $admin->id,
            'reference' => $payment->reference,
        ]);

        $payment->refresh();

        if ($payment->reviewed_at === null) {
            $payment->forceFill([
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
        }

        return $booking;
    }
}
