import { Pagination } from '@/components/catalog/pagination';
import { ServiceCard } from '@/components/catalog/service-card';
import { HeroBanners, StripBanners } from '@/components/marketing/banners';
import { FaqSection } from '@/components/marketing/faq-section';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type Banner, type Category, type Faq, type Paginated, type Service, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderOpen, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CatalogIndexProps {
    categories: Category[];
    featured: Service[];
    search: string;
    results: Paginated<Service> | null;
    banners: { hero: Banner[]; strip: Banner[] };
    faqs: Faq[];
}

export default function CatalogIndex({ categories, featured, search, results, banners, faqs }: CatalogIndexProps) {
    const { name } = usePage<SharedData>().props;
    const t = useTrans();
    const [term, setTerm] = useState(search);

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('catalog.index'), term !== '' ? { search: term } : {}, { preserveState: true });
    };

    return (
        <PublicLayout>
            <Head title={t('Home services, on demand')} />

            {results === null && <HeroBanners banners={banners.hero} />}

            <section className="mx-auto max-w-2xl py-8 text-center">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{t('Home services, on demand')}</h1>
                <p className="text-muted-foreground mt-3">
                    {t('Book trusted professionals with :name — cleaning, repairs, beauty and more, at your doorstep.', { name })}
                </p>
                <form onSubmit={submitSearch} className="mt-6 flex gap-2">
                    <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={t('What do you need help with?')} className="h-11" />
                    <Button type="submit" className="h-11">
                        <Search className="h-4 w-4" />
                        {t('Search')}
                    </Button>
                </form>
            </section>

            {results !== null ? (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        {results.meta.total === 0
                            ? t('No results for “:search”', { search })
                            : t(':total result(s) for “:search”', { total: results.meta.total, search })}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {results.data.map((service) => (
                            <ServiceCard key={service.id} service={service} />
                        ))}
                    </div>
                    <Pagination meta={results.meta} links={results.links} />
                </section>
            ) : (
                <>
                    <section className="space-y-4 py-4">
                        <h2 className="text-lg font-semibold">{t('Browse by category')}</h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map((category) => (
                                <Link key={category.id} href={route('catalog.category', category.slug)} prefetch className="group">
                                    <Card className="h-full py-0 transition-shadow group-hover:shadow-md">
                                        <CardContent className="flex items-center gap-4 p-4">
                                            {category.icon_url ? (
                                                <img src={category.icon_url} alt="" className="h-12 w-12 rounded-lg object-cover" />
                                            ) : (
                                                <div className="bg-muted flex h-12 w-12 items-center justify-center rounded-lg">
                                                    <FolderOpen className="text-muted-foreground h-6 w-6" />
                                                </div>
                                            )}
                                            <div>
                                                <h3 className="font-medium">{category.name}</h3>
                                                {category.children && category.children.length > 0 && (
                                                    <p className="text-muted-foreground line-clamp-1 text-sm">
                                                        {category.children.map((child) => child.name).join(' · ')}
                                                    </p>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                        {categories.length === 0 && <p className="text-muted-foreground">{t('The catalog is being set up. Check back soon.')}</p>}
                    </section>

                    <StripBanners banners={banners.strip} />

                    {featured.length > 0 && (
                        <section className="space-y-4 py-4">
                            <h2 className="text-lg font-semibold">{t('Popular services')}</h2>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {featured.map((service) => (
                                    <ServiceCard key={service.id} service={service} />
                                ))}
                            </div>
                        </section>
                    )}

                    <FaqSection faqs={faqs} />
                </>
            )}
        </PublicLayout>
    );
}
