<?php

namespace App\Domain\Earnings\Enums;

enum EarningStatus: string
{
    /** Inside the payouts.hold_days window — not yet claimable. */
    case Pending = 'pending';

    /** Counts toward the provider's payable balance. */
    case Available = 'available';

    /** Settled by a paid payout request. */
    case PaidOut = 'paid_out';
}
