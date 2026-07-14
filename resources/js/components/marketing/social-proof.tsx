import { Section, SectionHeading } from '@/components/site/section';
import { Card } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { type Sponsor, type Testimonial } from '@/types';
import { Quote, Star } from 'lucide-react';

/**
 * Admin-owned social proof on the storefront (M19): quotes and partner logos.
 * Both render nothing when the admin has added nothing — an empty section is
 * worse than no section.
 */
export function Testimonials({ testimonials, heading = null }: { testimonials: Testimonial[]; heading?: string | null }) {
    const t = useTrans();

    if (testimonials.length === 0) {
        return null;
    }

    return (
        <Section tone="surface" spacing="lg">
            <SectionHeading eyebrow={t('Reviews')} title={heading ?? t('What our customers say')} align="center" />

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {testimonials.map((testimonial) => (
                    <Card key={testimonial.id} className="card-lift relative flex h-full flex-col gap-4 p-6">
                        <Quote className="text-primary/15 absolute top-5 right-5 h-10 w-10" aria-hidden />

                        {testimonial.rating !== null && (
                            <div className="flex gap-0.5" aria-label={t(':count of 5', { count: String(testimonial.rating) })}>
                                {Array.from({ length: 5 }).map((_, index) => (
                                    <Star
                                        key={index}
                                        className={
                                            index < (testimonial.rating ?? 0)
                                                ? 'text-highlight fill-highlight h-4 w-4'
                                                : 'text-muted-foreground/25 h-4 w-4'
                                        }
                                        aria-hidden
                                    />
                                ))}
                            </div>
                        )}

                        <p className="relative flex-1 leading-relaxed">“{testimonial.quote}”</p>

                        <div className="flex items-center gap-3 border-t pt-4">
                            {testimonial.avatar_url !== null && (
                                <img src={testimonial.avatar_url} alt="" loading="lazy" className="h-10 w-10 rounded-full object-cover" />
                            )}
                            <div>
                                <p className="text-sm font-semibold">{testimonial.name}</p>
                                {testimonial.role !== null && <p className="text-muted-foreground text-xs">{testimonial.role}</p>}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </Section>
    );
}

export function Sponsors({ sponsors, heading = null }: { sponsors: Sponsor[]; heading?: string | null }) {
    const t = useTrans();

    if (sponsors.length === 0) {
        return null;
    }

    return (
        <Section spacing="md">
            <p className="text-muted-foreground mb-8 text-center text-xs font-semibold tracking-[0.14em] uppercase">{heading ?? t('Trusted by')}</p>

            <div className="flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
                {sponsors.map((sponsor) =>
                    sponsor.logo_url === null ? null : sponsor.link_url === null ? (
                        <img
                            key={sponsor.id}
                            src={sponsor.logo_url}
                            alt={sponsor.name}
                            loading="lazy"
                            className="h-9 opacity-55 grayscale transition dark:invert"
                        />
                    ) : (
                        <a key={sponsor.id} href={sponsor.link_url} target="_blank" rel="noopener noreferrer">
                            <img
                                src={sponsor.logo_url}
                                alt={sponsor.name}
                                loading="lazy"
                                className="h-9 opacity-55 grayscale transition hover:opacity-100 hover:grayscale-0 dark:invert"
                            />
                        </a>
                    ),
                )}
            </div>
        </Section>
    );
}
