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
            <div className={cn('from-primary/80 to-primary flex h-full w-full items-center justify-center bg-gradient-to-r px-6', className)}>
                <span className="text-center text-lg font-semibold text-white sm:text-2xl">{banner.title}</span>
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
        <section aria-label={t('Highlights')} className="pt-6">
            <div className="h-44 overflow-hidden rounded-2xl sm:h-64">
                <BannerFace banner={active} />
            </div>
            {banners.length > 1 && (
                <div className="mt-2 flex justify-center gap-1.5">
                    {banners.map((banner, i) => (
                        <button
                            key={banner.id}
                            type="button"
                            aria-label={t('Show banner :number', { number: i + 1 })}
                            onClick={() => setIndex(i)}
                            className={cn('h-1.5 w-4 rounded-full transition-colors', i === index ? 'bg-primary' : 'bg-muted')}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

export function StripBanners({ banners }: { banners: Banner[] }) {
    const t = useTrans();

    if (banners.length === 0) {
        return null;
    }

    return (
        <section aria-label={t('Offers')} className="py-4">
            <div className="flex gap-4 overflow-x-auto pb-1">
                {banners.map((banner) => (
                    <div key={banner.id} className="h-24 w-72 shrink-0 overflow-hidden rounded-xl sm:h-28 sm:w-96">
                        <BannerFace banner={banner} className="text-sm sm:text-base" />
                    </div>
                ))}
            </div>
        </section>
    );
}
