import { Section } from '@/components/site/section';

export interface StatsProps {
    heading: string | null;
    items: { value: string; label: string }[];
}

/**
 * The numbers band. Inverted on purpose: it is the one full-contrast strip on
 * the page, which is what makes it read as a claim rather than as another card.
 * Rationed to one — a page with three inverted bands has none.
 */
export function StatsBlock({ heading, items }: StatsProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Section tone="contrast" spacing="md">
            {heading !== null && <h2 className="mb-10 text-center text-2xl font-semibold tracking-tight sm:text-3xl">{heading}</h2>}

            <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                {items.map((item, index) => (
                    <div key={index} className="text-center">
                        <p className="text-4xl font-bold tracking-tight sm:text-5xl">{item.value}</p>
                        <p className="mt-2 text-sm font-medium opacity-70">{item.label}</p>
                    </div>
                ))}
            </div>
        </Section>
    );
}
