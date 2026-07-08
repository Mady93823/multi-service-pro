<?php

namespace App\Domain\Bookings\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartialRefund = 'partial_refund';
}
