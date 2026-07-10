<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->route('coupon');

        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon->id)];

        return $rules;
    }
}
