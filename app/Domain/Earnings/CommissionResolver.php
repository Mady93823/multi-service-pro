<?php

namespace App\Domain\Earnings;

use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Category;

/**
 * Resolves what the platform keeps from a booking (M09).
 *
 * A rate is looked up per booking item: the item's category, then its parent,
 * then the global payments.commission_percent setting — the first non-null
 * override wins. Commission is charged on the **pre-tax** service value; the
 * platform remits the GST it collected, so the provider never earns on it.
 */
class CommissionResolver
{
    /** Depth cap: categories are two levels today, but a cycle must not hang. */
    private const MAX_DEPTH = 10;

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function globalRate(): float
    {
        return $this->clamp($this->settings->decimal('payments.commission_percent', 0.0));
    }

    public function rateForCategory(?Category $category): float
    {
        for ($depth = 0; $category !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($category->commission_percent !== null) {
                return $this->clamp((float) $category->commission_percent);
            }

            $category = $category->parent;
        }

        return $this->globalRate();
    }

    /**
     * Gross is the taxable service value (subtotal + addons − discount), and
     * commission is the sum of each line's own category rate applied to that
     * line's share of it. `rate` is the blended percentage that produced the
     * commission — what gets snapshotted onto the booking.
     *
     * @return array{gross: float, commission: float, rate: float}
     */
    public function forBooking(Booking $booking): array
    {
        $gross = round(
            (float) $booking->subtotal + (float) $booking->addon_total - (float) $booking->discount,
            2,
        );

        // Soft-deleted catalog rows still have to price their historical
        // bookings, so every hop in the chain includes trashed records.
        $items = $booking->items()
            ->with(['service' => fn ($service) => $service->withTrashed()
                ->with(['category' => fn ($category) => $category->withTrashed()
                    ->with(['parent' => fn ($parent) => $parent->withTrashed()])])])
            ->get();

        $lineTotals = $items->map(fn (BookingItem $item): float => (float) $item->lineTotal());
        $lineSum = (float) $lineTotals->sum();
        $discount = (float) $booking->discount;

        $commission = 0.0;

        foreach ($items as $index => $item) {
            $line = (float) $lineTotals[$index];
            // Spread a booking-level discount across lines by value, so the
            // commission base always adds back up to `gross` (coupons, M12).
            $share = $lineSum > 0.0 ? $line / $lineSum : 0.0;
            $base = $line - $discount * $share;

            $commission += $base * $this->rateForCategory($item->service?->category) / 100;
        }

        $commission = round(max($commission, 0.0), 2);

        return [
            'gross' => $gross,
            'commission' => $commission,
            'rate' => $gross > 0.0 ? round($commission / $gross * 100, 2) : 0.0,
        ];
    }

    private function clamp(float $rate): float
    {
        return min(max($rate, 0.0), 100.0);
    }
}
