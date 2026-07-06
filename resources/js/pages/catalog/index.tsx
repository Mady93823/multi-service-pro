import { Pagination } from '@/components/catalog/pagination';
import { ServiceCard } from '@/components/catalog/service-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/public-layout';
import { type Category, type Paginated, type Service, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderOpen, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CatalogIndexProps {
    categories: Category[];
    featured: Service[];
    search: string;
    results: Paginated<Service> | null;
}

export default function CatalogIndex({ categories, featured, search, results }: CatalogIndexProps) {
    const { name } = usePage<SharedData>().props;
    const [term, setTerm] = useState(search);

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('catalog.index'), term !== '' ? { search: term } : {}, { preserveState: true });
    };

    return (
        <PublicLayout>
            <Head title="Home services, on demand" />

            <section className="mx-auto max-w-2xl py-8 text-center">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Home services, on demand</h1>
                <p className="text-muted-foreground mt-3">
                    Book trusted professionals with {name} — cleaning, repairs, beauty and more, at your doorstep.
                </p>
                <form onSubmit={submitSearch} className="mt-6 flex gap-2">
                    <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="What do you need help with?" className="h-11" />
                    <Button type="submit" className="h-11">
                        <Search className="h-4 w-4" />
                        Search
                    </Button>
                </form>
            </section>

            {results !== null ? (
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        {results.meta.total === 0 ? 'No results for' : `${results.meta.total} result(s) for`} “{search}”
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
                        <h2 className="text-lg font-semibold">Browse by category</h2>
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
                        {categories.length === 0 && <p className="text-muted-foreground">The catalog is being set up. Check back soon.</p>}
                    </section>

                    {featured.length > 0 && (
                        <section className="space-y-4 py-4">
                            <h2 className="text-lg font-semibold">Popular services</h2>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {featured.map((service) => (
                                    <ServiceCard key={service.id} service={service} />
                                ))}
                            </div>
                        </section>
                    )}
                </>
            )}
        </PublicLayout>
    );
}
