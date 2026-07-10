<?php

namespace App\Domain\Coupons\Actions;

use App\Models\Coupon;

class CreateCoupon
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Coupon
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return Coupon::query()->create($data);
    }
}
