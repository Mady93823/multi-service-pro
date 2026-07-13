import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Search, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Subscriber {
    id: number;
    email: string;
    source: string;
    subscribed: boolean;
    created_at: string | null;
}

interface SubscribersIndexProps {
    subscribers: Paginated<Subscriber>;
    filters: { search: string };
    stats: { total: number; subscribed: number };
}

export default function SubscribersIndex({ subscribers, filters, stats }: SubscribersIndexProps) {
    const t = useTrans();
    const [search, setSearch] = useState(filters.search);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Subscribers'), href: '/admin/subscribers' },
    ];

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.subscribers.index'), search !== '' ? { search } : {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Subscribers')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('Newsletter subscribers')}</h1>
                    {/* CSV rides the M13 export pipeline — no second export path. */}
                    <Button asChild variant="outline">
                        <Link href={route('admin.reports.export', 'subscribers')}>
                            <Download className="h-4 w-4" />
                            {t('Export CSV')}
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-xs">{t('Total')}</p>
                            <p className="text-lg font-semibold">{stats.total}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-muted-foreground text-xs">{t('Subscribed')}</p>
                            <p className="text-lg font-semibold">{stats.subscribed}</p>
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submitSearch} className="flex items-center gap-2">
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search email...')} className="w-64" />
                    <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                        <Search className="h-4 w-4" />
                    </Button>
                </form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Email')}</TableHead>
                                <TableHead>{t('Source')}</TableHead>
                                <TableHead>{t('Joined')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {subscribers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-10 text-center text-sm">
                                        {t('No subscribers yet.')}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                subscribers.data.map((subscriber) => (
                                    <TableRow key={subscriber.id}>
                                        <TableCell className="font-medium">{subscriber.email}</TableCell>
                                        <TableCell className="text-muted-foreground">{subscriber.source}</TableCell>
                                        <TableCell className="text-muted-foreground">{subscriber.created_at}</TableCell>
                                        <TableCell>
                                            {subscriber.subscribed ? (
                                                <Badge variant="secondary">{t('Subscribed')}</Badge>
                                            ) : (
                                                <Badge variant="outline">{t('Unsubscribed')}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {subscriber.subscribed && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t('Unsubscribe')}
                                                    onClick={() =>
                                                        router.delete(route('admin.subscribers.destroy', subscriber.id), {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={subscribers.meta} links={subscribers.links} />
            </div>
        </AdminLayout>
    );
}
