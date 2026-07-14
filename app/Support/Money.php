<?php

namespace App\Support;

use App\Domain\Settings\SettingsRegistry;

/**
 * Server-side counterpart of resources/js/lib/format.ts — used where React
 * cannot reach (invoice PDFs, emails, SMS).
 *
 * Formatting is settings-driven (M24, ADR D23): symbol, position, decimals and
 * digit grouping all come from the Currency screen, and there is **one currency
 * per install**. This never converts anything — a booking's money columns are
 * snapshots in the platform currency, and changing the screen changes how they
 * are printed, never what they are worth.
 *
 * Indian grouping is hand-rolled rather than taken from ext-intl, which shared
 * hosts often omit (D8).
 */
class Money
{
    /** Fallback symbols, used until an admin has saved one of their own. */
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    /** Fixed-point string for a decimal column: "1234.56", "-190.00". Never formatted. */
    public static function decimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * There is no currency argument on purpose (D23): one currency per install.
     * A caller cannot ask for a different one, because the platform does not
     * have one to give — and a booking's stored amounts are snapshots in the
     * platform currency, not values to convert.
     */
    public static function format(float|string $amount): string
    {
        $settings = app(SettingsRegistry::class);

        $code = $settings->string('localization.currency', 'INR');
        $symbol = $settings->string('currency.symbol') ?: (self::SYMBOLS[$code] ?? $code.' ');
        $decimals = max(0, min(2, $settings->integer('currency.decimals', 2)));
        $grouping = $settings->string('currency.grouping', 'indian');

        $value = (float) $amount;
        $magnitude = abs($value);

        $digits = $grouping === 'indian'
            ? self::indianGrouping($magnitude, $decimals)
            : number_format($magnitude, $decimals);

        $sign = $value < 0 ? '-' : '';

        return $settings->string('currency.position', 'before') === 'after'
            ? $sign.$digits.$symbol
            : $sign.$symbol.$digits;
    }

    /** 1,00,000.00 rather than 100,000.00 — the last three digits, then pairs. */
    private static function indianGrouping(float $value, int $decimals = 2): string
    {
        $formatted = number_format($value, $decimals, '.', '');
        [$whole, $fraction] = $decimals > 0 ? explode('.', $formatted) : [$formatted, ''];

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3));
            $whole = $rest.','.$lastThree;
        }

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
