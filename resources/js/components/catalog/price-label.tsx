import { type PricingType } from '@/types';

interface PriceLabelProps {
    price: string;
    pricingType: PricingType;
    className?: string;
}

/**
 * Currency symbol intentionally omitted until the settings registry (M14)
 * provides the configured currency — no hardcoded branding or locale.
 */
export function PriceLabel({ price, pricingType, className }: PriceLabelProps) {
    const amount = Number(price).toLocaleString();

    return (
        <span className={className}>
            {pricingType === 'hourly' && `${amount} / hour`}
            {pricingType === 'fixed' && amount}
            {pricingType === 'inspection' && `From ${amount} (after inspection)`}
        </span>
    );
}
