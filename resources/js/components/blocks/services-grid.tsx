import { ServiceCard } from '@/components/catalog/service-card';
import { type Service } from '@/types';

export interface ServicesGridProps {
    heading: string | null;
    services: Service[];
}

export function ServicesGridBlock({ heading, services }: ServicesGridProps) {
    if (services.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4 py-4">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {services.map((service) => (
                    <ServiceCard key={service.id} service={service} />
                ))}
            </div>
        </section>
    );
}
