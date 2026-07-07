import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
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
    const t = useTrans();
    const amount = money(price);

    return (
        <span className={className}>
            {pricingType === 'hourly' && t(':amount / hour', { amount })}
            {pricingType === 'fixed' && amount}
            {pricingType === 'inspection' && t('From :amount (after inspection)', { amount })}
        </span>
    );
}
