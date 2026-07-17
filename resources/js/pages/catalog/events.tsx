import { CategoryCard } from '@/components/catalog/category-card';
import { ServiceCard } from '@/components/catalog/service-card';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type Category, type Service } from '@/types';
import { Head } from '@inertiajs/react';
import { PartyPopper } from 'lucide-react';

interface EventsPageProps {
    categories: Category[];
    featured: Service[];
}

/**
 * The dedicated Event Management surface (weddings, birthdays, kitty parties).
 * Cards open the ordinary catalog routes — browsing, cart and booking are the
 * same machinery as every other service; only this listing page is its own.
 */
export default function EventsIndex({ categories, featured }: EventsPageProps) {
    const t = useTrans();

    return (
        <PublicLayout>
            <Head title={t('Event Management')} />

            <section className="mx-auto max-w-2xl py-8 text-center sm:py-12">
                <span className="bg-primary/10 text-primary mx-auto flex h-14 w-14 items-center justify-center rounded-2xl">
                    <PartyPopper className="h-7 w-7" />
                </span>
                <h1 className="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">{t('Event Management')}</h1>
                <p className="text-muted-foreground mt-3">
                    {t('Weddings, birthday parties, kitty parties and more — book a team the same way you book any service.')}
                </p>
            </section>

            <div className="space-y-14 pb-16">
                <section className="space-y-6">
                    <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">{t('Browse events')}</h2>

                    {categories.length === 0 ? (
                        <p className="text-muted-foreground">{t('Event packages are being set up. Check back soon.')}</p>
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {categories.map((category) => (
                                <CategoryCard key={category.id} category={category} />
                            ))}
                        </div>
                    )}
                </section>

                {featured.length > 0 && (
                    <section className="space-y-6">
                        <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">{t('Popular event services')}</h2>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {featured.map((service) => (
                                <ServiceCard key={service.id} service={service} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}
