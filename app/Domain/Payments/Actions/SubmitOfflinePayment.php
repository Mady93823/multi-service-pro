<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The customer's half of an offline payment (M22, D27): they transferred the
 * money themselves and are telling us so.
 *
 * This creates an ordinary payments row — gateway `offline`, state `initiated`
 * — with the proof on the private disk. Nothing is settled here: the booking
 * stays `pending_payment` until an admin verifies the transfer, which is what
 * keeps M08's invariant intact (an unpaid booking is never dispatched).
 *
 * A re-submission after a rejection is a *new* row: the failed attempt and its
 * reason stay on record.
 */
class SubmitOfflinePayment
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function handle(Booking $booking, BankAccount $account, ?string $reference, ?UploadedFile $proof): Payment
    {
        if (! $this->settings->boolean('payments.offline_enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => __('This payment method is not available right now.'),
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'bank_account_id' => __('This payment method is not available right now.'),
            ]);
        }

        $payment = DB::transaction(function () use ($booking, $account, $reference): Payment {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::PendingPayment) {
                throw ValidationException::withMessages([
                    'payment' => __('This booking is not awaiting payment.'),
                ]);
            }

            // One open declaration at a time — a second submission updates the
            // pending row rather than queueing a second thing for the admin to
            // verify against the same booking.
            /** @var Payment|null $open */
            $open = $locked->payments()
                ->awaitingVerification()
                ->lockForUpdate()
                ->first();

            $attributes = [
                'bank_account_id' => $account->id,
                'reference' => $reference,
                'amount' => $locked->total,
                'currency' => $this->settings->string('localization.currency', 'INR') ?: 'INR',
            ];

            if ($open !== null) {
                $open->forceFill($attributes)->save();

                return $open;
            }

            /** @var Payment $payment */
            $payment = $locked->payments()->create($attributes + [
                'gateway' => PaymentProvider::Offline->value,
                'status' => PaymentState::Initiated->value,
            ]);

            return $payment;
        });

        // singleFile(): a re-upload against the same open row replaces the old
        // proof rather than stacking two screenshots on one payment.
        if ($proof !== null) {
            $payment->addMedia($proof)->toMediaCollection('proof');
        }

        return $payment;
    }
}
