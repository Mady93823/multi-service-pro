<?php

namespace App\Domain\Support\Enums;

enum TicketCategory: string
{
    case Booking = 'booking';
    case Payment = 'payment';
    case Account = 'account';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Booking => __('Booking issue'),
            self::Payment => __('Payment & refunds'),
            self::Account => __('Account'),
            self::Other => __('Something else'),
        };
    }
}
