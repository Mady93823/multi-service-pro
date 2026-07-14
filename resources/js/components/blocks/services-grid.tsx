import { ServiceCard } from '@/components/catalog/service-card';
import { Section, SectionHeading } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { type Service } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export interface ServicesGridProps {
    heading: string | null;
    services: Service[];
}

export function ServicesGridBlock({ heading, services }: ServicesGridProps) {
    const t = useTrans();

    if (services.length === 0) {
        return null;
    }

    return (
        <Section tone="surface" spacing="lg">
            <SectionHeading
                eyebrow={t('Most booked')}
                title={heading ?? t('Popular services')}
                description={t('The jobs our customers book most often this month.')}
                action={
                    <Button asChild variant="outline" className="gap-1.5">
                        <Link href={route('catalog.index')}>
                            {t('Browse all')}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {services.map((service) => (
                    <ServiceCard key={service.id} service={service} />
                ))}
            </div>
        </Section>
    );
}
