import { Section, SectionHeading } from '@/components/site/section';
import { useTrans } from '@/lib/i18n';

export interface StepsProps {
    heading: string | null;
    items: { title: string; description: string | null }[];
}

/**
 * "How it works". The connecting rule between the numbers is drawn once, behind
 * the row, and hidden on small screens where the steps stack vertically and the
 * line would run through nothing.
 */
export function StepsBlock({ heading, items }: StepsProps) {
    const t = useTrans();

    if (items.length === 0) {
        return null;
    }

    return (
        <Section spacing="lg">
            <SectionHeading eyebrow={t('How it works')} title={heading ?? t('Booked in three steps')} align="center" />

            <ol className="relative grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    className="via-border absolute top-6 right-12 left-12 hidden h-px bg-gradient-to-r from-transparent to-transparent lg:block"
                    aria-hidden
                />

                {items.map((item, index) => (
                    <li key={index} className="relative text-center">
                        <span className="bg-primary text-primary-foreground ring-background mx-auto flex h-12 w-12 items-center justify-center rounded-2xl text-lg font-bold shadow-lg ring-8">
                            {index + 1}
                        </span>
                        <h3 className="mt-5 text-lg font-semibold">{item.title}</h3>
                        {item.description !== null && <p className="text-muted-foreground mx-auto mt-2 max-w-xs text-sm">{item.description}</p>}
                    </li>
                ))}
            </ol>
        </Section>
    );
}
