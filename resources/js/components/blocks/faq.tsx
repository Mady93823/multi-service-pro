import { FaqSection } from '@/components/marketing/faq-section';
import { type Faq } from '@/types';

export interface FaqProps {
    heading: string | null;
    faqs: Faq[];
}

export function FaqBlock({ heading, faqs }: FaqProps) {
    return <FaqSection faqs={faqs} heading={heading} />;
}
