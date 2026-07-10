<?php

namespace App\Domain\Coupons\Actions;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

class DeleteCoupon
{
    /**
     * A redeemed coupon is part of the audit trail (ADR D18) — deactivate
     * it instead of deleting. The FK on coupon_usages backs this up with
     * restrictOnDelete.
     *
     * @throws ValidationException
     */
    public function handle(Coupon $coupon): void
    {
        if ($coupon->usages()->exists()) {
            throw ValidationException::withMessages([
                'coupon' => __('This coupon has been used and cannot be deleted — deactivate it instead.'),
            ]);
        }

        $coupon->delete();
    }
}
