import { PriceLabel } from '@/components/catalog/price-label';
import { ServiceCard } from '@/components/catalog/service-card';
import { useMoney } from '@/lib/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/public-layout';
import { type Service } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Clock, ImageIcon } from 'lucide-react';

interface CatalogShowProps {
    service: Service;
}

export default function CatalogShow({ service }: CatalogShowProps) {
    const money = useMoney();

    return (
        <PublicLayout>
            <Head title={service.name} />

            <nav className="text-muted-foreground flex items-center gap-1 text-sm">
                <Link href={route('catalog.index')} className="hover:text-foreground">
                    Services
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
                            <h2 className="mb-2 text-lg font-semibold">About this service</h2>
                            <p className="text-muted-foreground whitespace-pre-line">{service.description}</p>
                        </section>
                    )}
                </div>

                <div className="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-2">
                                <CardTitle className="text-xl">{service.name}</CardTitle>
                                {service.is_featured && <Badge variant="secondary">Popular</Badge>}
                            </div>
                            {service.short_description && <p className="text-muted-foreground text-sm">{service.short_description}</p>}
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-4">
                                <PriceLabel price={service.price} pricingType={service.pricing_type} className="text-2xl font-semibold" />
                                {service.duration_minutes !== null && (
                                    <span className="text-muted-foreground flex items-center gap-1 text-sm">
                                        <Clock className="h-4 w-4" />
                                        {service.duration_minutes} min
                                    </span>
                                )}
                            </div>
                            <Button className="w-full" size="lg" disabled title="Booking opens in an upcoming release">
                                Book now — coming soon
                            </Button>
                        </CardContent>
                    </Card>

                    {service.addons && service.addons.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Available add-ons</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {service.addons.map((addon) => (
                                        <li key={addon.id} className="flex items-center justify-between text-sm">
                                            <span>{addon.name}</span>
                                            <span className="font-medium">{money(addon.price)}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {service.related && service.related.length > 0 && (
                <section className="space-y-4 py-4">
                    <h2 className="text-lg font-semibold">People also book</h2>
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
