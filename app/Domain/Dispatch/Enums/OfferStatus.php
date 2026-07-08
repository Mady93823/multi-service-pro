<?php

namespace App\Domain\Dispatch\Enums;

enum OfferStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return $this === self::Offered;
    }
}
