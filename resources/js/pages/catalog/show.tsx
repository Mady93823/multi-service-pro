import { PriceLabel } from '@/components/catalog/price-label';
import { ServiceCard } from '@/components/catalog/service-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Service } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronRight, Clock, ImageIcon, LoaderCircle, Minus, Plus, ShoppingCart } from 'lucide-react';
import { FormEventHandler } from 'react';

interface CatalogShowProps {
    service: Service;
    available_in_zone: boolean;
}

type AddToCartForm = {
    service_id: number;
    qty: number;
    addon_ids: number[];
};

export default function CatalogShow({ service, available_in_zone: availableInZone }: CatalogShowProps) {
    const money = useMoney();
    const t = useTrans();

    const { data, setData, post, processing } = useForm<AddToCartForm>({
        service_id: service.id,
        qty: 1,
        addon_ids: [],
    });

    const addonTotal = (service.addons ?? [])
        .filter((addon) => data.addon_ids.includes(addon.id))
        .reduce((sum, addon) => sum + Number(addon.price), 0);
    const total = (Number(service.price) + addonTotal) * data.qty;

    const toggleAddon = (addonId: number, checked: boolean) => {
        setData('addon_ids', checked ? [...data.addon_ids, addonId] : data.addon_ids.filter((id) => id !== addonId));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('cart.add'), { preserveScroll: true });
    };

    return (
        <PublicLayout>
            <Head title={service.name} />

            <nav className="text-muted-foreground flex items-center gap-1 text-sm">
                <Link href={route('catalog.index')} className="hover:text-foreground">
                    {t('Services')}
                </Link>
                <ChevronRight className="h-4 w-4" />
                {service.category && (
                    <>
                        <Link href={route('catalog.category', service.category.slug)} className="hover:text-foreground">
                            {service.category.name}
                        </Link>
                        <ChevronRight className="h-4 w-4" />
                    </>
                )}
                <span className="text-foreground">{service.name}</span>
            </nav>

            <div className="grid gap-8 py-6 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-3">
                    <div className="bg-muted flex aspect-video items-center justify-center overflow-hidden rounded-xl">
                        {service.image_hero_url ? (
                            <img src={service.image_hero_url} alt={service.name} className="h-full w-full object-cover" />
                        ) : (
                            <ImageIcon className="text-muted-foreground/40 h-16 w-16" />
                        )}
                    </div>

                    {service.description && (
                        <section>
                            <h2 className="mb-2 text-lg font-semibold">{t('About this service')}</h2>
                            <p className="text-muted-foreground whitespace-pre-line">{service.description}</p>
                        </section>
                    )}
                </div>

                <div className="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-2">
                                <CardTitle className="text-xl">{service.name}</CardTitle>
                                {service.is_featured && <Badge variant="secondary">{t('Popular')}</Badge>}
                            </div>
                            {service.short_description && <p className="text-muted-foreground text-sm">{service.short_description}</p>}
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-4">
                                <PriceLabel price={service.price} pricingType={service.pricing_type} className="text-2xl font-semibold" />
                                {service.duration_minutes !== null && (
                                    <span className="text-muted-foreground flex items-center gap-1 text-sm">
                                        <Clock className="h-4 w-4" />
                                        {t(':minutes min', { minutes: service.duration_minutes })}
                                    </span>
                                )}
                            </div>

                            {!availableInZone && (
                                <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                                    {t('This service is not yet available at your default address.')}
                                </p>
                            )}

                            <form onSubmit={submit} className="space-y-4">
                                {service.addons && service.addons.length > 0 && (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">{t('Available add-ons')}</p>
                                        {service.addons.map((addon) => (
                                            <label key={addon.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.addon_ids.includes(addon.id)}
                                                    onCheckedChange={(checked) => toggleAddon(addon.id, checked === true)}
                                                />
                                                <span className="flex-1">{addon.name}</span>
                                                <span className="font-medium">{money(addon.price)}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                <div className="flex items-center gap-3">
                                    <span className="text-sm font-medium">{t('Quantity')}</span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-8 w-8"
                                            aria-label={t('Decrease quantity')}
                                            disabled={data.qty <= 1}
                                            onClick={() => setData('qty', Math.max(1, data.qty - 1))}
                                        >
                                            <Minus className="h-3 w-3" />
                                        </Button>
                                        <span className="w-6 text-center text-sm font-medium">{data.qty}</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-8 w-8"
                                            aria-label={t('Increase quantity')}
                                            disabled={data.qty >= 10}
                                            onClick={() => setData('qty', Math.min(10, data.qty + 1))}
                                        >
                                            <Plus className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>

                                <Button type="submit" className="w-full" size="lg" disabled={processing}>
                                    {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <ShoppingCart className="h-4 w-4" />}
                                    {t('Add to cart — :total', { total: money(total) })}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {service.related && service.related.length > 0 && (
                <section className="space-y-4 py-4">
                    <h2 className="text-lg font-semibold">{t('People also book')}</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {service.related.map((related) => (
                            <ServiceCard key={related.id} service={related} />
                        ))}
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}
