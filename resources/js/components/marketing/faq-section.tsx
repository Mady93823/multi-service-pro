import { Section, SectionHeading } from '@/components/site/section';
import { useTrans } from '@/lib/i18n';
import { type Faq } from '@/types';
import { Plus } from 'lucide-react';

/**
 * Storefront FAQ accordion (M14). Native <details>/<summary> — accessible,
 * zero extra dependencies, and the browser handles open/close state (it also
 * means Ctrl+F finds the answer inside a closed row in Chrome).
 */
export function FaqSection({ faqs, heading = null }: { faqs: Faq[]; heading?: string | null }) {
    const t = useTrans();

    if (faqs.length === 0) {
        return null;
    }

    return (
        <Section spacing="lg">
            <SectionHeading eyebrow={t('Questions')} title={heading ?? t('Frequently asked questions')} align="center" />

            <div className="mx-auto max-w-3xl space-y-3">
                {faqs.map((faq) => (
                    <details
                        key={faq.id}
                        className="group bg-card hover:border-primary/30 rounded-xl border px-5 py-4 transition-colors open:shadow-sm"
                    >
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
                            {faq.question}
                            <span className="bg-muted text-muted-foreground group-open:bg-primary group-open:text-primary-foreground flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-colors">
                                <Plus className="h-4 w-4 transition-transform group-open:rotate-45" aria-hidden />
                            </span>
                        </summary>
                        <p className="text-muted-foreground mt-3 text-sm leading-relaxed whitespace-pre-line">{faq.answer}</p>
                    </details>
                ))}
            </div>
        </Section>
    );
}
