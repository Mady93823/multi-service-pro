import { Card, CardContent } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { type Category } from '@/types';
import { Link } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';

export interface CategoriesGridProps {
    heading: string | null;
    categories: Category[];
}

export function CategoriesGridBlock({ heading, categories }: CategoriesGridProps) {
    const t = useTrans();

    if (categories.length === 0) {
        return <p className="text-muted-foreground py-4">{t('The catalog is being set up. Check back soon.')}</p>;
    }

    return (
        <section className="space-y-4 py-4">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
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
        </section>
    );
}
