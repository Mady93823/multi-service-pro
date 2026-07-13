<?php

namespace App\Domain\Bookings\Enums;

/**
 * Gateway methods (razorpay/stripe/wallet) activate in Phase 4 (M08);
 * cash = pay after service, enabled at launch; offline = bank transfer the
 * customer makes themselves and an admin verifies (M22).
 *
 * Only cash is settled at the door: an offline booking waits in
 * pending_payment exactly like a gateway one, so it is never dispatched
 * unpaid, and its money reaches the platform's account — not the provider's
 * pocket — which is why RecordBookingEarning treats it as collected by us.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Gateway = 'gateway';
    case Wallet = 'wallet';
    case Offline = 'offline';
}
