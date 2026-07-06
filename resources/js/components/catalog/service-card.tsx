import { PriceLabel } from '@/components/catalog/price-label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { type Service } from '@/types';
import { Link } from '@inertiajs/react';
import { Clock, ImageIcon } from 'lucide-react';

interface ServiceCardProps {
    service: Service;
}

export function ServiceCard({ service }: ServiceCardProps) {
    if (!service.category) {
        return null;
    }

    return (
        <Link href={route('catalog.show', [service.category.slug, service.slug])} prefetch className="group">
            <Card className="h-full overflow-hidden py-0 transition-shadow group-hover:shadow-md">
                <div className="bg-muted flex aspect-video items-center justify-center overflow-hidden">
                    {service.image_card_url ? (
                        <img
                            src={service.image_card_url}
                            alt={service.name}
                            className="h-full w-full object-cover transition-transform group-hover:scale-105"
                        />
                    ) : (
                        <ImageIcon className="text-muted-foreground/40 h-10 w-10" />
                    )}
                </div>
                <CardContent className="space-y-2 p-4">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="leading-tight font-medium">{service.name}</h3>
                        {service.is_featured && <Badge variant="secondary">Popular</Badge>}
                    </div>
                    {service.short_description && <p className="text-muted-foreground line-clamp-2 text-sm">{service.short_description}</p>}
                    <div className="text-muted-foreground flex items-center gap-3 text-sm">
                        <PriceLabel price={service.price} pricingType={service.pricing_type} className="text-foreground font-semibold" />
                        {service.duration_minutes !== null && (
                            <span className="flex items-center gap-1">
                                <Clock className="h-3.5 w-3.5" />
                                {service.duration_minutes} min
                            </span>
                        )}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
