import { Section, SectionHeading } from '@/components/site/section';
import { useTrans } from '@/lib/i18n';

export interface GalleryProps {
    heading: string | null;
    items: { url: string; thumb_url: string; caption: string | null }[];
}

export function GalleryBlock({ heading, items }: GalleryProps) {
    const t = useTrans();

    if (items.length === 0) {
        return null;
    }

    return (
        <Section spacing="lg">
            {heading !== null && <SectionHeading eyebrow={t('Gallery')} title={heading} align="center" />}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item, index) => (
                    <figure key={index} className="group bg-card card-lift overflow-hidden rounded-2xl border">
                        <div className="overflow-hidden">
                            <img
                                src={item.thumb_url}
                                alt={item.caption ?? ''}
                                loading="lazy"
                                className="h-56 w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.05]"
                            />
                        </div>
                        {item.caption !== null && <figcaption className="text-muted-foreground px-4 py-3 text-sm">{item.caption}</figcaption>}
                    </figure>
                ))}
            </div>
        </Section>
    );
}
