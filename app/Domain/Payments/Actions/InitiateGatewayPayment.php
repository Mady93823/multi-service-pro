<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\GatewayManager;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * Get (or reuse) an open payment attempt for a pending-payment booking and
 * hand back the gateway session the pay page needs. Reuse matters: a page
 * refresh must not create a fresh upstream order every time.
 */
class InitiateGatewayPayment
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @return array{payment: Payment, session: array<string, mixed>}
     */
    public function handle(Booking $booking, PaymentProvider $provider): array
    {
        if ($booking->status !== BookingStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'payment' => __('This booking is not awaiting payment.'),
            ]);
        }

        $gateway = $this->gateways->get($provider);

        if ($gateway === null || ! $gateway->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => __('This payment method is not available right now.'),
            ]);
        }

        /** @var Payment $payment */
        $payment = $booking->payments()->firstOrCreate(
            [
                'gateway' => $provider->value,
                'status' => PaymentState::Initiated->value,
                'amount' => $booking->total,
            ],
            [
                'currency' => $this->settings->string('localization.currency', 'INR') ?: 'INR',
            ],
        );

        return [
            'payment' => $payment,
            'session' => $gateway->session($booking, $payment),
        ];
    }
}
