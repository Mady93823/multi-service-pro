<?php

namespace App\Support;

/**
 * Server-side counterpart of resources/js/lib/format.ts — used where React
 * cannot reach (invoice PDFs, mails). Indian digit grouping is hand-rolled
 * rather than taken from ext-intl, which shared hosts often omit (D8).
 */
class Money
{
    private const SYMBOLS = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    /** Fixed-point string for a decimal column: "1234.56", "-190.00". */
    public static function decimal(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function format(float|string $amount, string $currency = 'INR'): string
    {
        $value = (float) $amount;
        $magnitude = abs($value);

        $digits = $currency === 'INR'
            ? self::indianGrouping($magnitude)
            : number_format($magnitude, 2);

        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        return ($value < 0 ? '-' : '').$symbol.$digits;
    }

    /** 1,00,000.00 rather than 100,000.00 — the last three digits, then pairs. */
    private static function indianGrouping(float $value): string
    {
        [$whole, $fraction] = explode('.', number_format($value, 2, '.', ''));

        if (strlen($whole) <= 3) {
            return $whole.'.'.$fraction;
        }

        $lastThree = substr($whole, -3);
        $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3));

        return $rest.','.$lastThree.'.'.$fraction;
    }
}
