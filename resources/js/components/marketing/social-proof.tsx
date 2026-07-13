import { Card, CardContent } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { type Sponsor, type Testimonial } from '@/types';
import { Star } from 'lucide-react';

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
        <section className="space-y-4 py-4">
            <h2 className="text-lg font-semibold">{heading ?? t('What our customers say')}</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {testimonials.map((testimonial) => (
                    <Card key={testimonial.id}>
                        <CardContent className="space-y-3 pt-6">
                            {testimonial.rating !== null && (
                                <div className="flex gap-0.5" aria-label={t(':count of 5', { count: String(testimonial.rating) })}>
                                    {Array.from({ length: testimonial.rating }).map((_, index) => (
                                        <Star key={index} className="h-4 w-4 fill-current text-amber-500" />
                                    ))}
                                </div>
                            )}
                            <p className="text-sm">“{testimonial.quote}”</p>
                            <div className="flex items-center gap-3">
                                {testimonial.avatar_url !== null && (
                                    <img src={testimonial.avatar_url} alt="" className="h-8 w-8 rounded-full object-cover" />
                                )}
                                <div>
                                    <p className="text-sm font-medium">{testimonial.name}</p>
                                    {testimonial.role !== null && <p className="text-muted-foreground text-xs">{testimonial.role}</p>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}

export function Sponsors({ sponsors, heading = null }: { sponsors: Sponsor[]; heading?: string | null }) {
    const t = useTrans();

    if (sponsors.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4 py-4">
            <h2 className="text-muted-foreground text-sm font-medium">{heading ?? t('Trusted by')}</h2>
            <div className="flex flex-wrap items-center gap-6">
                {sponsors.map((sponsor) =>
                    sponsor.logo_url === null ? null : sponsor.link_url === null ? (
                        <img key={sponsor.id} src={sponsor.logo_url} alt={sponsor.name} className="h-10 opacity-70" />
                    ) : (
                        <a key={sponsor.id} href={sponsor.link_url} target="_blank" rel="noopener noreferrer">
                            <img src={sponsor.logo_url} alt={sponsor.name} className="h-10 opacity-70 transition hover:opacity-100" />
                        </a>
                    ),
                )}
            </div>
        </section>
    );
}
