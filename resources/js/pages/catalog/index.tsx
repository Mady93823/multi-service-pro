import { CategoryCard } from '@/components/catalog/category-card';
import { Pagination } from '@/components/catalog/pagination';
import { ServiceCard } from '@/components/catalog/service-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type Category, type Paginated, type Service } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Search, SearchX } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CatalogIndexProps {
    categories: Category[];
    featured: Service[];
    search: string;
    results: Paginated<Service> | null;
}

/**
 * The services page. The marketing sections that used to sit here moved to the
 * home page as blocks (M20) — this screen is the catalog and its search.
 */
export default function CatalogIndex({ categories, featured, search, results }: CatalogIndexProps) {
    const t = useTrans();
    const [term, setTerm] = useState(search);

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('catalog.index'), term !== '' ? { search: term } : {}, { preserveState: true });
    };

    return (
        <PublicLayout>
            <Head title={t('All services')} />

            <section className="mx-auto max-w-2xl py-8 text-center sm:py-12">
                <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{t('All services')}</h1>
                <p className="text-muted-foreground mt-3">{t('Search for a job, or browse the categories below.')}</p>

                <form
                    onSubmit={submitSearch}
                    className="bg-card focus-within:ring-ring/40 mt-8 flex items-center gap-2 rounded-2xl border p-2 shadow-sm focus-within:ring-2"
                >
                    <Search className="text-muted-foreground ml-2 h-5 w-5 shrink-0" aria-hidden />
                    <Input
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder={t('What do you need help with?')}
                        aria-label={t('Search services')}
                        className="h-11 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0 dark:bg-transparent"
                    />
                    <Button type="submit" className="h-11 shrink-0 rounded-xl px-5">
                        {t('Search')}
                    </Button>
                </form>
            </section>

            {results !== null ? (
                <section className="space-y-8 pb-12">
                    <h2 className="text-xl font-semibold">
                        {results.meta.total === 0
                            ? t('No results for “:search”', { search })
                            : t(':total result(s) for “:search”', { total: results.meta.total, search })}
                    </h2>

                    {results.meta.total === 0 ? (
                        <div className="flex flex-col items-center gap-4 py-16 text-center">
                            <span className="bg-muted text-muted-foreground flex h-14 w-14 items-center justify-center rounded-2xl">
                                <SearchX className="h-6 w-6" />
                            </span>
                            <p className="text-muted-foreground max-w-sm">{t('Try a different word, or browse the categories.')}</p>
                            <Button variant="outline" onClick={() => router.get(route('catalog.index'))}>
                                {t('Browse all')}
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {results.data.map((service) => (
                                    <ServiceCard key={service.id} service={service} />
                                ))}
                            </div>
                            <Pagination meta={results.meta} links={results.links} />
                        </>
                    )}
                </section>
            ) : (
                <div className="space-y-14 pb-16">
                    <section className="space-y-6">
                        <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">{t('Browse by category')}</h2>

                        {categories.length === 0 ? (
                            <p className="text-muted-foreground">{t('The catalog is being set up. Check back soon.')}</p>
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
                            <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">{t('Popular services')}</h2>
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {featured.map((service) => (
                                    <ServiceCard key={service.id} service={service} />
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            )}
        </PublicLayout>
    );
}
