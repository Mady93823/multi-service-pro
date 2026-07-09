<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\WalletService;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wallet is an instant internal gateway (M08): debit the ledger, record the
 * settlement, place the booking. An insufficient balance throws before
 * anything changes — the booking stays pending_payment and the pay page
 * offers the other methods.
 */
class PayWithWallet
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly BookingStateMachine $machine,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(Booking $booking, User $customer): Booking
    {
        if ($booking->status !== BookingStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'payment' => __('This booking is not awaiting payment.'),
            ]);
        }

        if (! $this->settings->boolean('payments.wallet_enabled', true)) {
            throw ValidationException::withMessages([
                'payment' => __('This payment method is not available right now.'),
            ]);
        }

        $booking = DB::transaction(function () use ($booking, $customer): Booking {
            // Throws (and rolls everything back) when the balance is short.
            $this->wallet->debit(
                $customer,
                $booking->total,
                'payment',
                Booking::class,
                $booking->id,
                $booking->code,
            );

            $booking->payments()->create([
                'gateway' => PaymentProvider::Wallet,
                'amount' => $booking->total,
                'currency' => $this->settings->string('localization.currency', 'INR') ?: 'INR',
                'status' => PaymentState::Captured,
                'captured_at' => now(),
            ]);

            $booking->payment_status = PaymentStatus::Paid;
            $booking->payment_method = PaymentMethod::Wallet;
            $booking->save();

            return $this->machine->transition($booking, BookingStatus::Placed, BookingActor::System);
        });

        BookingPlaced::dispatch($booking);

        return $booking;
    }
}
