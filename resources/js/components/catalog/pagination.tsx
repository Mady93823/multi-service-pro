import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { type Paginated } from '@/types';
import { Link } from '@inertiajs/react';

interface PaginationProps {
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
}

export function Pagination({ meta, links }: PaginationProps) {
    const t = useTrans();

    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-muted-foreground text-sm">{t(':from–:to of :total', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total })}</p>
            <div className="flex gap-2">
                <Button asChild={links.prev !== null} variant="outline" size="sm" disabled={links.prev === null}>
                    {links.prev !== null ? (
                        <Link href={links.prev} preserveScroll>
                            {t('Previous')}
                        </Link>
                    ) : (
                        <span>{t('Previous')}</span>
                    )}
                </Button>
                <Button asChild={links.next !== null} variant="outline" size="sm" disabled={links.next === null}>
                    {links.next !== null ? (
                        <Link href={links.next} preserveScroll>
                            {t('Next')}
                        </Link>
                    ) : (
                        <span>{t('Next')}</span>
                    )}
                </Button>
            </div>
        </div>
    );
}
