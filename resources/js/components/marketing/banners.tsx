import { Container } from '@/components/site/section';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Banner } from '@/types';
import { useEffect, useState } from 'react';

/**
 * Admin-managed marketing banners (M12). A banner without an uploaded image
 * renders as a brand-gradient card with its title, so a fresh install never
 * shows a broken <img>.
 */

function BannerFace({ banner, className }: { banner: Banner; className?: string }) {
    const inner =
        banner.image_url !== null ? (
            <img src={banner.image_url} alt={banner.title} className={cn('h-full w-full object-cover', className)} />
        ) : (
            <div className={cn('from-primary to-primary/70 flex h-full w-full items-center justify-center bg-gradient-to-br px-6', className)}>
                <span className="text-primary-foreground text-center text-lg font-semibold sm:text-2xl">{banner.title}</span>
            </div>
        );

    if (banner.link_url !== null) {
        return (
            <a href={banner.link_url} rel="noopener" className="block h-full w-full">
                {inner}
            </a>
        );
    }

    return inner;
}

export function HeroBanners({ banners }: { banners: Banner[] }) {
    const t = useTrans();
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (banners.length < 2) {
            return;
        }

        const timer = window.setInterval(() => setIndex((current) => (current + 1) % banners.length), 5000);

        return () => window.clearInterval(timer);
    }, [banners.length]);

    if (banners.length === 0) {
        return null;
    }

    const active = banners[Math.min(index, banners.length - 1)];

    return (
        <Container className="py-8 sm:py-10">
            <section aria-label={t('Highlights')}>
                <div className="h-52 overflow-hidden rounded-2xl shadow-sm sm:h-72">
                    <BannerFace banner={active} />
                </div>

                {banners.length > 1 && (
                    <div className="mt-4 flex justify-center gap-2">
                        {banners.map((banner, i) => (
                            <button
                                key={banner.id}
                                type="button"
                                aria-label={t('Show banner :number', { number: i + 1 })}
                                aria-current={i === index}
                                onClick={() => setIndex(i)}
                                className={cn(
                                    'h-1.5 rounded-full transition-all duration-300',
                                    i === index ? 'bg-primary w-8' : 'bg-muted-foreground/25 hover:bg-muted-foreground/50 w-4',
                                )}
                            />
                        ))}
                    </div>
                )}
            </section>
        </Container>
    );
}

export function StripBanners({ banners }: { banners: Banner[] }) {
    const t = useTrans();

    if (banners.length === 0) {
        return null;
    }

    return (
        <Container className="py-6">
            <section aria-label={t('Offers')}>
                {/* Snap scrolling: a strip that stops half-way between two offers looks broken on a phone. */}
                <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                    {banners.map((banner) => (
                        <div key={banner.id} className="card-lift h-28 w-72 shrink-0 snap-start overflow-hidden rounded-2xl border sm:h-32 sm:w-96">
                            <BannerFace banner={banner} className="text-sm sm:text-base" />
                        </div>
                    ))}
                </div>
            </section>
        </Container>
    );
}
