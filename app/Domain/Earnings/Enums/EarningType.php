<?php

namespace App\Domain\Earnings\Enums;

/**
 * The ledger is append-only, so a mistake is corrected by writing an opposing
 * row rather than editing the original (04-Database-Schema integrity rules).
 */
enum EarningType: string
{
    /** The provider completed the booking. */
    case Job = 'job';

    /** The booking was refunded — negates the job row column for column. */
    case Reversal = 'reversal';

    /** Manual admin correction. */
    case Adjustment = 'adjustment';
}
