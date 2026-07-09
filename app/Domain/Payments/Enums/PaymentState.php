<?php

namespace App\Domain\Payments\Enums;

/**
 * Lifecycle of a single payments row. Distinct from the booking-level
 * App\Domain\Bookings\Enums\PaymentStatus (unpaid|paid|refunded|…), which
 * summarizes all attempts.
 */
enum PaymentState: string
{
    case Initiated = 'initiated';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
