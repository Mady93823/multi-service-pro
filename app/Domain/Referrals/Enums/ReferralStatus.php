<?php

namespace App\Domain\Referrals\Enums;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Rewarded = 'rewarded';
}
