<?php

namespace App\Domain\Bookings\Enums;

enum BookingActor: string
{
    case Customer = 'customer';
    case Provider = 'provider';
    case Admin = 'admin';
    case System = 'system';
}
