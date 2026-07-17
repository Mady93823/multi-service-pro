<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Gateways\PayPalGateway;
use App\Domain\Payments\Gateways\PayUGateway;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;

/**
 * Registry of online gateways (M08, extended D39). Razorpay first — UPI-first
 * India launch (D7); then PayU (the second Indian rail), Stripe and PayPal
 * (D8, international installs). Order here is display order at checkout.
 */
class GatewayManager
{
    public function __construct(
        private readonly RazorpayGateway $razorpay,
        private readonly PayUGateway $payu,
        private readonly StripeGateway $stripe,
        private readonly PayPalGateway $paypal,
    ) {}

    /**
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return [$this->razorpay, $this->payu, $this->stripe, $this->paypal];
    }

    /**
     * @return list<PaymentGateway>
     */
    public function configured(): array
    {
        return array_values(array_filter($this->all(), fn (PaymentGateway $gateway): bool => $gateway->isConfigured()));
    }

    public function get(PaymentProvider $provider): ?PaymentGateway
    {
        foreach ($this->all() as $gateway) {
            if ($gateway->provider() === $provider) {
                return $gateway;
            }
        }

        return null;
    }
}
