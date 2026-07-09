<?php

namespace App\Domain\Payments\Enums;

/**
 * Who settled the money (payments.gateway column). Razorpay and Stripe are
 * online gateways behind the PaymentGateway contract; cash and wallet are
 * internal settlements that never leave the platform.
 */
enum PaymentProvider: string
{
    case Razorpay = 'razorpay';
    case Stripe = 'stripe';
    case Cash = 'cash';
    case Wallet = 'wallet';

    public function isOnlineGateway(): bool
    {
        return $this === self::Razorpay || $this === self::Stripe;
    }
}
