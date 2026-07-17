import { Breadcrumbs } from '@/components/catalog/breadcrumbs';
import { PriceLabel } from '@/components/catalog/price-label';
import { ServiceCard } from '@/components/catalog/service-card';
import { ReviewCard } from '@/components/reviews/review-card';
import { StarRating } from '@/components/reviews/star-rating';
import { SeoHead } from '@/components/seo/seo-head';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Paginated, type Review, type ReviewSummary, type SeoMetaProps, type Service } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { BadgeCheck, Clock, ImageIcon, LoaderCircle, MapPin, Minus, Plus, ShieldCheck, ShoppingCart } from 'lucide-react';
import { FormEventHandler } from 'react';

interface CatalogShowProps {
    service: Service;
    available_in_zone: boolean;
    /** Quantity of this service already sitting in the cart, across all lines. */
    in_cart_qty: number;
    reviews: Paginated<Review> | null;
    review_summary: ReviewSummary | null;
    meta: SeoMetaProps;
    /** Service JSON-LD, or null when structured data is switched off (M24). */
    schema: Record<string, unknown> | null;
}

type AddToCartForm = {
    service_id: number;
    qty: number;
    addon_ids: number[];
    book_now: boolean;
};

export default function CatalogShow({
    service,
    available_in_zone: availableInZone,
    in_cart_qty: inCartQty,
    reviews,
    review_summary: reviewSummary,
    meta,
    schema,
}: CatalogShowProps) {
    const money = useMoney();
    const t = useTrans();

    const { data, setData, post, processing, transform } = useForm<AddToCartForm>({
        service_id: service.id,
        qty: 1,
        addon_ids: [],
        book_now: false,
    });

    const addons = service.addons ?? [];

    const addonTotal = addons.filter((addon) => data.addon_ids.includes(addon.id)).reduce((sum, addon) => sum + Number(addon.price), 0);
    const total = (Number(service.price) + addonTotal) * data.qty;

    const toggleAddon = (addonId: number, checked: boolean) => {
        setData('addon_ids', checked ? [...data.addon_ids, addonId] : data.addon_ids.filter((id) => id !== addonId));
    };

    /**
     * `transform` rather than setData('book_now', …): setData is React state and
     * would not have landed by the time post() reads the form, while transform
     * runs at submit time. It is set on every call — it sticks once assigned.
     */
    const add = (bookNow: boolean) => {
        transform((current) => ({ ...current, book_now: bookNow }));
        post(route('cart.add'));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        add(false);
    };

    const assurances = [
        { icon: BadgeCheck, label: t('Verified professional') },
        { icon: ShieldCheck, label: t('Pay after the job is done') },
        { icon: MapPin, label: t('Live tracking on the day') },
    ];

    return (
        <PublicLayout>
            <SeoHead meta={meta} schema={schema} />

            <Breadcrumbs
                items={[
                    { label: t('Services'), url: route('catalog.index') },
                    ...(service.category ? [{ label: service.category.name, url: route('catalog.category', service.category.slug) }] : []),
                    { label: service.name },
                ]}
            />

            <div className="grid gap-10 py-8 lg:grid-cols-5">
                <div className="space-y-8 lg:col-span-3">
                    <div className="bg-muted relative aspect-[16/10] overflow-hidden rounded-2xl">
                        {service.image_hero_url ? (
                            <img src={service.image_hero_url} alt={service.name} className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center">
                                <ImageIcon className="text-muted-foreground/30 h-16 w-16" />
                            </div>
                        )}

                        {service.is_featured && (
                            <span className="bg-highlight text-highlight-foreground absolute top-4 left-4 rounded-full px-3 py-1.5 text-xs font-bold tracking-wide uppercase shadow-sm">
                                {t('Popular')}
                            </span>
                        )}
                    </div>

                    <div>
                        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{service.name}</h1>

                        <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                            {reviewSummary !== null && reviewSummary.count > 0 && (
                                <span className="flex items-center gap-2">
                                    <StarRating rating={reviewSummary.average} />
                                    <span className="font-semibold">{reviewSummary.average.toFixed(1)}</span>
                                    <span className="text-muted-foreground">{t(':count reviews', { count: String(reviewSummary.count) })}</span>
                                </span>
                            )}

                            {service.duration_minutes !== null && (
                                <span className="text-muted-foreground flex items-center gap-1.5">
                                    <Clock className="h-4 w-4" />
                                    {t(':minutes min', { minutes: service.duration_minutes })}
                                </span>
                            )}
                        </div>

                        {service.short_description && <p className="text-muted-foreground mt-4 text-lg">{service.short_description}</p>}
                    </div>

                    <ul className="grid gap-3 border-y py-6 sm:grid-cols-3">
                        {assurances.map(({ icon: Icon, label }) => (
                            <li key={label} className="flex items-center gap-2.5 text-sm font-medium">
                                <span className="bg-primary/10 text-primary flex h-9 w-9 shrink-0 items-center justify-center rounded-xl">
                                    <Icon className="h-4.5 w-4.5" />
                                </span>
                                {label}
                            </li>
                        ))}
                    </ul>

                    {service.description && (
                        <section>
                            <h2 className="mb-3 text-xl font-semibold">{t('About this service')}</h2>
                            <p className="text-muted-foreground leading-relaxed whitespace-pre-line">{service.description}</p>
                        </section>
                    )}
                </div>

                {/* Sticky on a desktop: the price and the button are the reason this page exists, and scrolling the reviews must not take them off screen. */}
                <div className="lg:col-span-2">
                    <Card className="gap-0 p-6 shadow-lg lg:sticky lg:top-24">
                        <div className="flex items-end justify-between gap-3 border-b pb-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">{t('Starting at')}</p>
                                <PriceLabel price={service.price} pricingType={service.pricing_type} className="mt-1 text-3xl font-bold" />
                            </div>
                            {service.duration_minutes !== null && (
                                <span className="text-muted-foreground flex items-center gap-1 pb-1.5 text-sm">
                                    <Clock className="h-4 w-4" />
                                    {t(':minutes min', { minutes: service.duration_minutes })}
                                </span>
                            )}
                        </div>

                        {!availableInZone && (
                            <p className="border-highlight/40 bg-highlight/10 mt-5 rounded-xl border px-4 py-3 text-sm">
                                {t('This service is not yet available at your default address.')}
                            </p>
                        )}

                        {inCartQty > 0 && (
                            <p className="bg-muted mt-5 flex items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm">
                                <span className="flex items-center gap-2 font-medium">
                                    <ShoppingCart className="text-primary h-4 w-4 shrink-0" aria-hidden />
                                    {t(':count already in your cart', { count: inCartQty })}
                                </span>
                                <Link href={route('cart.show')} className="text-primary shrink-0 font-semibold hover:underline">
                                    {t('Go to cart')}
                                </Link>
                            </p>
                        )}

                        <form onSubmit={submit} className="mt-5 space-y-5">
                            {addons.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm font-semibold">{t('Available add-ons')}</p>

                                    {addons.map((addon) => (
                                        <label
                                            key={addon.id}
                                            className="hover:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5 flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-colors"
                                        >
                                            <Checkbox
                                                checked={data.addon_ids.includes(addon.id)}
                                                onCheckedChange={(checked) => toggleAddon(addon.id, checked === true)}
                                            />
                                            <span className="flex-1 font-medium">{addon.name}</span>
                                            <span className="font-semibold">{money(addon.price)}</span>
                                        </label>
                                    ))}
                                </div>
                            )}

                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold">{t('Quantity')}</span>
                                <div className="flex items-center gap-1 rounded-xl border p-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8"
                                        aria-label={t('Decrease quantity')}
                                        disabled={data.qty <= 1}
                                        onClick={() => setData('qty', Math.max(1, data.qty - 1))}
                                    >
                                        <Minus className="h-3.5 w-3.5" />
                                    </Button>
                                    <span className="w-8 text-center font-semibold">{data.qty}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8"
                                        aria-label={t('Increase quantity')}
                                        disabled={data.qty >= 10}
                                        onClick={() => setData('qty', Math.min(10, data.qty + 1))}
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between border-t pt-4">
                                <span className="font-semibold">{t('Total')}</span>
                                <span className="text-2xl font-bold">{money(total)}</span>
                            </div>

                            <div className="space-y-2">
                                <Button type="submit" className="h-12 w-full rounded-xl text-base" size="lg" disabled={processing}>
                                    {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShoppingCart className="h-4 w-4" />}
                                    {t('Add to cart')}
                                </Button>

                                {/* Straight to checkout — booking one service is the common case, and the cart page in the middle of it decides nothing. */}
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-12 w-full rounded-xl text-base"
                                    size="lg"
                                    disabled={processing}
                                    onClick={() => add(true)}
                                >
                                    {t('Book now')}
                                </Button>
                            </div>

                            <p className="text-muted-foreground text-center text-xs">{t('No payment now. You choose how to pay at checkout.')}</p>
                        </form>
                    </Card>
                </div>
            </div>

            {reviews !== null && reviewSummary !== null && reviewSummary.count > 0 && (
                <section className="space-y-8 border-t py-12">
                    <h2 className="text-2xl font-semibold tracking-tight">{t('Customer reviews')}</h2>

                    <div className="grid gap-10 lg:grid-cols-3">
                        <div className="space-y-5">
                            <div className="bg-surface rounded-2xl border p-6 text-center">
                                <p className="text-5xl font-bold tracking-tight">{reviewSummary.average.toFixed(1)}</p>
                                <div className="mt-2 flex justify-center">
                                    <StarRating rating={reviewSummary.average} />
                                </div>
                                <p className="text-muted-foreground mt-2 text-sm">{t(':count reviews', { count: String(reviewSummary.count) })}</p>
                            </div>

                            <div className="space-y-2">
                                {[5, 4, 3, 2, 1].map((stars) => {
                                    const count = reviewSummary.distribution[stars] ?? 0;
                                    const percent = reviewSummary.count > 0 ? (count / reviewSummary.count) * 100 : 0;

                                    return (
                                        <div key={stars} className="flex items-center gap-3 text-xs">
                                            <span className="text-muted-foreground w-3 text-right font-medium">{stars}</span>
                                            <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                <div
                                                    className="bg-highlight h-full rounded-full transition-[width]"
                                                    style={{ width: `${percent}%` }}
                                                />
                                            </div>
                                            <span className="text-muted-foreground w-8 tabular-nums">{count}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="space-y-4 lg:col-span-2">
                            {reviews.data.map((review) => (
                                <ReviewCard key={review.id} review={review} />
                            ))}

                            {(reviews.links.prev !== null || reviews.links.next !== null) && (
                                <div className="flex gap-2">
                                    {reviews.links.prev !== null && (
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={reviews.links.prev} preserveScroll>
                                                {t('Previous')}
                                            </Link>
                                        </Button>
                                    )}
                                    {reviews.links.next !== null && (
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={reviews.links.next} preserveScroll>
                                                {t('More reviews')}
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </section>
            )}

            {service.related && service.related.length > 0 && (
                <section className="space-y-6 border-t py-12">
                    <h2 className="text-2xl font-semibold tracking-tight">{t('People also book')}</h2>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {service.related.map((related) => (
                            <ServiceCard key={related.id} service={related} />
                        ))}
                    </div>
                </section>
            )}

            {/* The buy box is a right-hand column, so on a phone it sits below the hero, the description and the reviews — a long scroll away from the one thing this page is for. This keeps the price and the button reachable. */}
            <div className="bg-background/95 fixed inset-x-0 bottom-0 z-40 flex items-center gap-4 border-t px-4 py-3 backdrop-blur lg:hidden">
                <div className="min-w-0 flex-1">
                    <p className="text-muted-foreground text-xs">{t('Total')}</p>
                    <p className="truncate text-lg font-bold">{money(total)}</p>
                </div>
                <Button type="button" size="lg" className="h-11 shrink-0 rounded-xl" disabled={processing} onClick={() => add(false)}>
                    {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShoppingCart className="h-4 w-4" />}
                    {t('Add to cart')}
                </Button>
            </div>

            {/* The bar floats, so the last of the page needs somewhere to go. */}
            <div className="h-20 lg:hidden" aria-hidden />
        </PublicLayout>
    );
}
