import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface Crumb {
    label: string;
    /** The last crumb is the page you are on, so it has no link. */
    url?: string;
}

/**
 * The storefront trail. It existed twice, hand-rolled, with two different sets
 * of spacing — and neither was a `<nav aria-label>`, so a screen reader met an
 * anonymous row of links.
 */
export function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb" className="text-muted-foreground flex flex-wrap items-center gap-1 text-sm">
            {items.map((item, index) => (
                <span key={`${item.label}-${index}`} className="flex items-center gap-1">
                    {index > 0 && <ChevronRight className="h-3.5 w-3.5 shrink-0 opacity-50" aria-hidden />}

                    {item.url !== undefined ? (
                        <Link href={item.url} className="hover:text-foreground transition-colors">
                            {item.label}
                        </Link>
                    ) : (
                        <span className="text-foreground font-medium" aria-current="page">
                            {item.label}
                        </span>
                    )}
                </span>
            ))}
        </nav>
    );
}
