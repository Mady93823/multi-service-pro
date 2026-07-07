import { Pagination } from '@/components/catalog/pagination';
import { ServiceCard } from '@/components/catalog/service-card';
import { Badge } from '@/components/ui/badge';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type Category, type Paginated, type Service } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface CatalogCategoryProps {
    category: Category;
    services: Paginated<Service>;
}

export default function CatalogCategory({ category, services }: CatalogCategoryProps) {
    const t = useTrans();

    return (
        <PublicLayout>
            <Head title={category.name} />

            <nav className="text-muted-foreground flex items-center gap-1 text-sm">
                <Link href={route('catalog.index')} className="hover:text-foreground">
                    {t('Services')}
                </Link>
                <ChevronRight className="h-4 w-4" />
                <span className="text-foreground">{category.name}</span>
            </nav>

            <section className="py-6">
                <h1 className="text-2xl font-semibold">{category.name}</h1>
                {category.children && category.children.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {category.children.map((child) => (
                            <Badge key={child.id} variant="secondary">
                                {child.name}
                            </Badge>
                        ))}
                    </div>
                )}
            </section>

            <section className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {services.data.map((service) => (
                        <ServiceCard key={service.id} service={service} />
                    ))}
                </div>
                {services.data.length === 0 && <p className="text-muted-foreground">{t('No services in this category yet.')}</p>}
                <Pagination meta={services.meta} links={services.links} />
            </section>
        </PublicLayout>
    );
}
