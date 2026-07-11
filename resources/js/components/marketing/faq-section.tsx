import { useTrans } from '@/lib/i18n';
import { type Faq } from '@/types';
import { ChevronDown } from 'lucide-react';

/**
 * Storefront FAQ accordion (M14). Native <details>/<summary> — accessible,
 * zero extra dependencies, and the browser handles open/close state.
 */
export function FaqSection({ faqs }: { faqs: Faq[] }) {
    const t = useTrans();

    if (faqs.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4 py-4">
            <h2 className="text-lg font-semibold">{t('Frequently asked questions')}</h2>
            <div className="divide-y rounded-xl border">
                {faqs.map((faq) => (
                    <details key={faq.id} className="group px-4 py-3">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium [&::-webkit-details-marker]:hidden">
                            {faq.question}
                            <ChevronDown className="text-muted-foreground h-4 w-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden />
                        </summary>
                        <p className="text-muted-foreground mt-2 text-sm whitespace-pre-line">{faq.answer}</p>
                    </details>
                ))}
            </div>
        </section>
    );
}
