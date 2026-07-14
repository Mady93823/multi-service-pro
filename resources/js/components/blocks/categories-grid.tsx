import { CategoryCard } from '@/components/catalog/category-card';
import { Section, SectionHeading } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { type Category } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export interface CategoriesGridProps {
    heading: string | null;
    categories: Category[];
}

export function CategoriesGridBlock({ heading, categories }: CategoriesGridProps) {
    const t = useTrans();

    if (categories.length === 0) {
        return (
            <Section spacing="sm">
                <p className="text-muted-foreground text-center">{t('The catalog is being set up. Check back soon.')}</p>
            </Section>
        );
    }

    return (
        <Section spacing="lg">
            <SectionHeading
                eyebrow={t('Categories')}
                title={heading ?? t('What do you need done?')}
                description={t('Pick a category and book a vetted professional in your area.')}
                action={
                    <Button asChild variant="ghost" className="gap-1.5">
                        <Link href={route('catalog.index')}>
                            {t('All services')}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </Button>
                }
            />

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {categories.map((category) => (
                    <CategoryCard key={category.id} category={category} />
                ))}
            </div>
        </Section>
    );
}
