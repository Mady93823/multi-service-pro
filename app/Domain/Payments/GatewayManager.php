<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;

/**
 * Registry of online gateways (M08). Razorpay first — UPI-first India launch
 * (D7); Stripe second (D8). Order here is display order at checkout.
 */
class GatewayManager
{
    public function __construct(
        private readonly RazorpayGateway $razorpay,
        private readonly StripeGateway $stripe,
    ) {}

    /**
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return [$this->razorpay, $this->stripe];
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
