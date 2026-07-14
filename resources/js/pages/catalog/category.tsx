import { Breadcrumbs } from '@/components/catalog/breadcrumbs';
import { Pagination } from '@/components/catalog/pagination';
import { ServiceCard } from '@/components/catalog/service-card';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type Category, type Paginated, type Service } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';

interface CatalogCategoryProps {
    category: Category;
    services: Paginated<Service>;
}

export default function CatalogCategory({ category, services }: CatalogCategoryProps) {
    const t = useTrans();

    const children = category.children ?? [];

    return (
        <PublicLayout>
            <Head title={category.name} />

            <Breadcrumbs items={[{ label: t('Services'), url: route('catalog.index') }, { label: category.name }]} />

            <header className="mt-6 border-b pb-8">
                <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{category.name}</h1>
                <p className="text-muted-foreground mt-2">{t(':total service(s) available', { total: services.meta.total })}</p>

                {children.length > 0 && (
                    <div className="mt-5 flex flex-wrap gap-2">
                        {children.map((child) => (
                            <Link
                                key={child.id}
                                href={route('catalog.category', child.slug)}
                                className="bg-muted hover:bg-primary hover:text-primary-foreground rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                            >
                                {child.name}
                            </Link>
                        ))}
                    </div>
                )}
            </header>

            {services.data.length === 0 ? (
                <div className="flex flex-col items-center gap-3 py-20 text-center">
                    <span className="bg-muted text-muted-foreground flex h-14 w-14 items-center justify-center rounded-2xl">
                        <PackageOpen className="h-6 w-6" />
                    </span>
                    <p className="text-muted-foreground">{t('No services in this category yet.')}</p>
                </div>
            ) : (
                <section className="space-y-8 py-10">
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {services.data.map((service) => (
                            <ServiceCard key={service.id} service={service} />
                        ))}
                    </div>
                    <Pagination meta={services.meta} links={services.links} />
                </section>
            )}
        </PublicLayout>
    );
}
