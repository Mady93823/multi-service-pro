<?php

namespace App\Domain\Bookings;

use App\Domain\Settings\SettingsRegistry;
use App\Models\ServiceAddon;

/**
 * One price computation for both the checkout preview and PlaceBooking —
 * the number the customer saw is the number that gets snapshotted.
 *
 * @phpstan-import-type DetailedLine from CartManager
 */
class PriceQuote
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Pre-discount, pre-tax order value — the base coupons are judged
     * against and discount percentages are taken from (M12).
     *
     * @param  list<DetailedLine>  $lines
     */
    public function baseFor(array $lines): float
    {
        $base = 0.0;

        foreach ($lines as $line) {
            $base += (float) $line['service']->price * $line['qty'];
            $base += (float) $line['addons']->sum(
                fn (ServiceAddon $addon): float => (float) $addon->price
            ) * $line['qty'];
        }

        return $base;
    }

    /**
     * @param  list<DetailedLine>  $lines
     * @return array{subtotal: float, addon_total: float, discount: float, tax: float, cgst: float, sgst: float, total: float, tax_label: string, tax_percent: float}
     */
    public function fromLines(array $lines, float $discount = 0.0): array
    {
        $subtotal = 0.0;
        $addonTotal = 0.0;

        foreach ($lines as $line) {
            $subtotal += (float) $line['service']->price * $line['qty'];
            $addonTotal += (float) $line['addons']->sum(
                fn (ServiceAddon $addon): float => (float) $addon->price
            ) * $line['qty'];
        }

        // Discount applies before tax (a coupon reduces the taxable value)
        // and can never exceed the order value.
        $discount = round(min(max($discount, 0.0), $subtotal + $addonTotal), 2);
        $taxable = $subtotal + $addonTotal - $discount;
        $percent = $this->settings->decimal('payments.tax_percent', 0.0);
        $tax = round($taxable * $percent / 100, 2);
        // Single-city launch = intra-state supply: equal CGST + SGST halves
        // (IGST stays 0 until inter-state supply exists).
        $cgst = round($tax / 2, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'addon_total' => round($addonTotal, 2),
            'discount' => round($discount, 2),
            'tax' => $tax,
            'cgst' => $cgst,
            'sgst' => round($tax - $cgst, 2),
            'total' => round($taxable + $tax, 2),
            'tax_label' => $this->settings->string('payments.tax_label', 'GST'),
            'tax_percent' => $percent,
        ];
    }
}
