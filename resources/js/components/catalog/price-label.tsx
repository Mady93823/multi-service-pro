import { useMoney } from '@/lib/format';
import { type PricingType } from '@/types';

interface PriceLabelProps {
    price: string;
    pricingType: PricingType;
    className?: string;
}

/**
 * Money display driven by the settings registry (M14): currency code comes
 * from shared localization props — no hardcoded symbol or locale (D8/D9).
 */
export function PriceLabel({ price, pricingType, className }: PriceLabelProps) {
    const money = useMoney();
    const amount = money(price);

    return (
        <span className={className}>
            {pricingType === 'hourly' && `${amount} / hour`}
            {pricingType === 'fixed' && amount}
            {pricingType === 'inspection' && `From ${amount} (after inspection)`}
        </span>
    );
}
