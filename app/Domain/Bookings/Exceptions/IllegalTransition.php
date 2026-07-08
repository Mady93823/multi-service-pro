<?php

namespace App\Domain\Bookings\Exceptions;

use App\Domain\Bookings\Enums\BookingStatus;
use DomainException;

class IllegalTransition extends DomainException
{
    public static function between(BookingStatus $from, BookingStatus $to): self
    {
        return new self("Booking cannot move from [{$from->value}] to [{$to->value}].");
    }
}
