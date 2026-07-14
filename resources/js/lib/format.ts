import { type Localization, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Indian grouping: the last three digits, then pairs — 1,00,000.00.
 * Hand-rolled so the browser and `App\Support\Money` print the same string,
 * rather than trusting two different locale databases to agree (D8).
 */
function group(digits: string, grouping: string): string {
    if (grouping !== 'indian' || digits.length <= 3) {
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    const lastThree = digits.slice(-3);
    const rest = digits.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');

    return `${rest},${lastThree}`;
}

/**
 * Format an amount in the platform currency (M24, ADR D23).
 *
 * Symbol, position, decimals and grouping all come from the Currency settings
 * screen. There is one currency per install — this prints money, it never
 * converts it.
 */
export function formatMoney(amount: string | number, localization: Localization): string {
    const value = typeof amount === 'string' ? Number(amount) : amount;
    const decimals = Math.min(2, Math.max(0, localization.decimals ?? 2));

    const fixed = Math.abs(value).toFixed(decimals);
    const [whole, fraction] = fixed.split('.');

    const digits =
        fraction === undefined ? group(whole, localization.grouping ?? 'indian') : `${group(whole, localization.grouping ?? 'indian')}.${fraction}`;

    const sign = value < 0 ? '-' : '';
    const symbol = localization.symbol ?? '₹';

    return localization.position === 'after' ? `${sign}${digits}${symbol}` : `${sign}${symbol}${digits}`;
}

/** Convenience hook: platform money formatter bound to shared localization props. */
export function useMoney(): (amount: string | number) => string {
    const { localization } = usePage<SharedData>().props;

    return (amount) => formatMoney(amount, localization);
}
