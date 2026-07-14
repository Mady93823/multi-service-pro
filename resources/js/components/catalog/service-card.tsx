import { PriceLabel } from '@/components/catalog/price-label';
import { Card } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Service } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Clock, ImageIcon } from 'lucide-react';

interface ServiceCardProps {
    service: Service;
    className?: string;
}

/**
 * The most-repeated object on the storefront, so it carries the weight.
 *
 * Deliberately **no star rating**: ratings in this product belong to providers,
 * not to services (M10 recomputes `rating_avg` on `provider_profiles`). A card
 * that showed stars here would either be inventing them or forcing a per-row
 * query into every grid on the site — the exact N+1 P7.2 went hunting for.
 */
export function ServiceCard({ service, className }: ServiceCardProps) {
    const t = useTrans();

    if (!service.category) {
        return null;
    }

    return (
        <Link href={route('catalog.show', [service.category.slug, service.slug])} prefetch className={cn('group block', className)}>
            <Card className="card-lift h-full gap-0 overflow-hidden py-0">
                <div className="bg-muted relative aspect-[4/3] overflow-hidden">
                    {service.image_card_url ? (
                        <img
                            src={service.image_card_url}
                            alt={service.name}
                            loading="lazy"
                            className="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.06]"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center">
                            <ImageIcon className="text-muted-foreground/30 h-10 w-10" />
                        </div>
                    )}

                    {service.is_featured && (
                        <span className="bg-highlight text-highlight-foreground absolute top-3 left-3 rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide uppercase shadow-sm">
                            {t('Popular')}
                        </span>
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-2 p-4">
                    <h3 className="group-hover:text-primary leading-snug font-semibold transition-colors">{service.name}</h3>

                    {service.short_description && <p className="text-muted-foreground line-clamp-2 text-sm">{service.short_description}</p>}

                    <div className="mt-auto flex items-end justify-between gap-2 pt-2">
                        <div className="space-y-1">
                            <PriceLabel price={service.price} pricingType={service.pricing_type} className="text-foreground text-lg font-bold" />
                            {service.duration_minutes !== null && (
                                <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                    <Clock className="h-3.5 w-3.5" />
                                    {t(':minutes min', { minutes: service.duration_minutes })}
                                </span>
                            )}
                        </div>

                        <span className="text-primary group-hover:bg-primary group-hover:text-primary-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full border transition-colors group-hover:border-transparent">
                            <ArrowRight className="h-4 w-4" />
                        </span>
                    </div>
                </div>
            </Card>
        </Link>
    );
}
