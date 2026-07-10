<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\CartManager;
use App\Domain\Coupons\Actions\ApplyCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ApplyCouponRequest;
use Illuminate\Http\RedirectResponse;

class CouponController extends Controller
{
    public function store(ApplyCouponRequest $request, ApplyCoupon $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $coupon = $action->handle($user, (string) $request->validated('coupon'));

        return back()->with('success', __('Coupon :code applied.', ['code' => $coupon->code]));
    }

    public function destroy(CartManager $cart): RedirectResponse
    {
        $cart->setCouponCode(null);

        return back()->with('success', __('Coupon removed.'));
    }
}
