import { type Localization, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

const localeFor = (currency: string): string => (currency === 'INR' ? 'en-IN' : 'en');

/**
 * Format an amount in the platform currency (settings-driven, D8/D9).
 * INR gets Indian digit grouping (1,00,000) via the en-IN locale.
 */
export function formatMoney(amount: string | number, localization: Localization): string {
    const value = typeof amount === 'string' ? Number(amount) : amount;

    return new Intl.NumberFormat(localeFor(localization.currency), {
        style: 'currency',
        currency: localization.currency,
        maximumFractionDigits: Number.isInteger(value) ? 0 : 2,
    }).format(value);
}

/** Convenience hook: platform money formatter bound to shared localization props. */
export function useMoney(): (amount: string | number) => string {
    const { localization } = usePage<SharedData>().props;

    return (amount) => formatMoney(amount, localization);
}
