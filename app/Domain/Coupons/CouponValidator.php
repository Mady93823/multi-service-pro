<?php

namespace App\Domain\Coupons;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Coupons\Enums\CouponType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The single eligibility + discount path for coupons (ADR D18). Both the
 * checkout "apply" endpoint and PlaceBooking call this — the preview and
 * the placement can never disagree on the rules, only on timing, which is
 * why PlaceBooking re-runs it under a lock on the coupon row.
 */
class CouponValidator
{
    /**
     * Statuses that release a redemption back to the pool: the payment
     * window lapsed or the gateway failed — money never moved, the
     * customer never got anything. Everything else (including a cancel
     * after payment) stays spent, UC parity.
     */
    private const RELEASING_STATUSES = [
        BookingStatus::Expired,
        BookingStatus::FailedPayment,
    ];

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Validate every cap and return the discount for the given pre-tax
     * base (subtotal + add-ons). Throws with a per-rule message.
     *
     * @throws ValidationException
     */
    public function discountFor(Coupon $coupon, User $user, float $base): float
    {
        if (! $coupon->is_active) {
            throw $this->fail(__('This coupon is no longer active.'));
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            throw $this->fail(__('This coupon is not active yet.'));
        }

        if ($coupon->ends_at !== null && $coupon->ends_at->isPast()) {
            throw $this->fail(__('This coupon has expired.'));
        }

        if ($coupon->min_order !== null && $base < (float) $coupon->min_order) {
            throw $this->fail(__('This coupon needs a minimum order of :amount.', [
                'amount' => number_format((float) $coupon->min_order, 2),
            ]));
        }

        if ($coupon->first_order_only && $this->hasOrderedBefore($user)) {
            throw $this->fail(__('This coupon is only valid on your first order.'));
        }

        if ($coupon->usage_limit !== null
            && $this->countedUsages($coupon)->count() >= $coupon->usage_limit) {
            throw $this->fail(__('This coupon has been fully redeemed.'));
        }

        if ($coupon->per_user_limit !== null
            && $this->countedUsages($coupon)->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            throw $this->fail(__('You have already used this coupon the maximum number of times.'));
        }

        $discount = $coupon->type === CouponType::Flat
            ? (float) $coupon->value
            : $base * (float) $coupon->value / 100;

        if ($coupon->type === CouponType::Percent && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $base), 2);
    }

    /**
     * "First order" means no earlier booking that committed money or a
     * provider's time: pending/failed/expired payment attempts don't count,
     * a cancelled booking does (ADR D18).
     */
    private function hasOrderedBefore(User $user): bool
    {
        return Booking::query()
            ->where('customer_id', $user->id)
            ->whereNotIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::FailedPayment->value,
                BookingStatus::Expired->value,
            ])
            ->exists();
    }

    /**
     * Usages that count against the caps. Rows are never deleted (audit
     * trail) — instead a usage whose booking died unpaid stops counting.
     *
     * @return Builder<CouponUsage>
     */
    private function countedUsages(Coupon $coupon): Builder
    {
        return CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->whereHas('booking', fn (Builder $query) => $query->whereNotIn(
                'status',
                array_map(fn (BookingStatus $status): string => $status->value, self::RELEASING_STATUSES),
            ));
    }

    private function fail(string $message): ValidationException
    {
        return ValidationException::withMessages(['coupon' => $message]);
    }
}
