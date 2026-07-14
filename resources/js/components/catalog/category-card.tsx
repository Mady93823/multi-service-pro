import { Card } from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Category } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Sparkles } from 'lucide-react';

/**
 * One category tile, in one place.
 *
 * This markup previously existed twice — once in the categories block, once on
 * the services page — and the two copies had already drifted. A tile that
 * appears on two screens is a component, not a paragraph you re-type.
 *
 * Everything lives **inside the image**. The sub-category list used to sit in a
 * strip under it, which meant a category with no children rendered an empty
 * white bar and the row came out ragged — and a long list clipped mid-line,
 * which reads as a broken page, not as truncation. One shape, every card, every
 * time.
 */
export function CategoryCard({ category, className }: { category: Category; className?: string }) {
    const t = useTrans();

    const children = category.children ?? [];
    const image = category.image_url ?? category.icon_url;

    // The children are the honest subtitle; the count is the fallback so the
    // line is never empty and the cards never disagree about their height.
    const subtitle =
        children.length > 0
            ? children.map((child) => child.name).join(' · ')
            : category.services_count !== undefined && category.services_count > 0
              ? t(':count service(s)', { count: category.services_count })
              : null;

    return (
        <Link href={route('catalog.category', category.slug)} prefetch className={cn('group block', className)}>
            <Card className="card-lift relative aspect-[4/3] gap-0 overflow-hidden py-0">
                {image !== null ? (
                    <img
                        src={image}
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.06]"
                    />
                ) : (
                    <div className="from-primary/30 via-primary/10 absolute inset-0 flex items-center justify-center bg-gradient-to-br to-transparent">
                        <Sparkles className="text-primary/50 h-8 w-8" />
                    </div>
                )}

                {/* Text sits on a photograph, so the photograph is darkened for it — not tinted for taste. */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent" />

                <span className="absolute top-3 right-3 flex h-8 w-8 translate-y-1 items-center justify-center rounded-full bg-white/15 text-white opacity-0 backdrop-blur-sm transition-all group-hover:translate-y-0 group-hover:opacity-100">
                    <ArrowUpRight className="h-4 w-4" />
                </span>

                <div className="absolute inset-x-0 bottom-0 p-4">
                    <h3 className="text-lg leading-tight font-semibold text-white">{category.name}</h3>
                    {subtitle !== null && <p className="mt-1 truncate text-xs font-medium text-white/75">{subtitle}</p>}
                </div>
            </Card>
        </Link>
    );
}
