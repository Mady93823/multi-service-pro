<?php

namespace App\Domain\Bookings\Enums;

/**
 * Gateway methods (razorpay/stripe/wallet) activate in Phase 4 (M08);
 * cash = pay after service, enabled at launch.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Gateway = 'gateway';
    case Wallet = 'wallet';
}
