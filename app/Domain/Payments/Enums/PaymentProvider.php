<?php

namespace App\Domain\Payments\Enums;

/**
 * Who settled the money (payments.gateway column). Razorpay and Stripe are
 * online gateways behind the PaymentGateway contract; cash and wallet are
 * internal settlements that never leave the platform; offline is a bank
 * transfer an admin verifies by hand (M22, D27) — still an ordinary payments
 * row, still settled through ConfirmPayment.
 */
enum PaymentProvider: string
{
    case Razorpay = 'razorpay';
    case Stripe = 'stripe';
    case Cash = 'cash';
    case Wallet = 'wallet';
    case Offline = 'offline';

    public function isOnlineGateway(): bool
    {
        return $this === self::Razorpay || $this === self::Stripe;
    }
}
