import { Button } from '@/components/ui/button';
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';

interface PaginationProps {
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
}

export function Pagination({ meta, links }: PaginationProps) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-muted-foreground text-sm">
                {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
            </p>
            <div className="flex gap-2">
                <Button asChild={links.prev !== null} variant="outline" size="sm" disabled={links.prev === null}>
                    {links.prev !== null ? (
                        <Link href={links.prev} preserveScroll>
                            Previous
                        </Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                <Button asChild={links.next !== null} variant="outline" size="sm" disabled={links.next === null}>
                    {links.next !== null ? (
                        <Link href={links.next} preserveScroll>
                            Next
                        </Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </div>
    );
}
