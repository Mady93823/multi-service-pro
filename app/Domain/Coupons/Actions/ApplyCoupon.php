<?php

namespace App\Domain\Coupons\Actions;

use App\Domain\Bookings\CartManager;
use App\Domain\Bookings\PriceQuote;
use App\Domain\Coupons\CouponValidator;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Checkout "apply" leg: validate against the current cart and remember the
 * code in the session. This is only a preview — PlaceBooking re-validates
 * under a lock on the coupon row before any usage is spent (ADR D18).
 */
class ApplyCoupon
{
    public function __construct(
        private readonly CouponValidator $validator,
        private readonly CartManager $cart,
        private readonly PriceQuote $quote,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $code): Coupon
    {
        $lines = $this->cart->detailed();

        if ($lines === []) {
            throw ValidationException::withMessages(['coupon' => __('Your cart is empty.')]);
        }

        $coupon = $this->validator->findByCode($code);

        if ($coupon === null) {
            throw ValidationException::withMessages(['coupon' => __('That coupon code is not valid.')]);
        }

        $this->validator->discountFor($coupon, $user, $this->quote->baseFor($lines));

        $this->cart->setCouponCode($coupon->code);

        return $coupon;
    }
}
