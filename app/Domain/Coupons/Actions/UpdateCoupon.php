<?php

namespace App\Domain\Coupons\Actions;

use App\Models\Coupon;

class UpdateCoupon
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Coupon $coupon, array $data): Coupon
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $coupon->update($data);

        return $coupon;
    }
}
