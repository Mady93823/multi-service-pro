<?php

namespace App\Domain\Bookings\Exceptions;

use DomainException;

class InvalidJobOtp extends DomainException
{
    public static function make(): self
    {
        return new self('The job start code does not match this booking.');
    }
}
