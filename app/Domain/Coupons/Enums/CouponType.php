<?php

namespace App\Domain\Coupons\Enums;

enum CouponType: string
{
    case Flat = 'flat';
    case Percent = 'percent';
}
