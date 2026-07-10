<?php

namespace App\Domain\Earnings\Enums;

enum PayoutStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    /**
     * An open request holds its earnings hostage: they cannot be claimed by a
     * second request until this one is paid or rejected.
     */
    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Approved;
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Requested, self::Approved];
    }
}
