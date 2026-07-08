import { Pagination } from '@/components/catalog/pagination';
import { ApprovalStatusBadge, useApprovalStatusLabels } from '@/components/provider/provider-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated, type ProviderApprovalStatus } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ALL = 'all';

interface ProviderRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    approval_status: ProviderApprovalStatus | null;
    is_online: boolean;
    is_complete: boolean;
    joined_at: string | null;
}

interface AdminProvidersIndexProps {
    providers: Paginated<ProviderRow>;
    filters: { status: string; search: string };
}

export default function AdminProvidersIndex({ providers, filters }: AdminProvidersIndexProps) {
    const t = useTrans();
    const statusLabels = useApprovalStatusLabels();
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status !== '' ? filters.status : ALL);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Providers'), href: '/admin/providers' },
    ];

    const applyFilters = (nextSearch: string, nextStatus: string) => {
        router.get(
            route('admin.providers.index'),
            {
                ...(nextSearch !== '' ? { search: nextSearch } : {}),
                ...(nextStatus !== ALL ? { status: nextStatus } : {}),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters(search, status);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Providers')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Providers')}</h1>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search by name or email...')}
                            className="w-64"
                        />
                        <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                    <Select
                        value={status}
                        onValueChange={(value) => {
                            setStatus(value);
                            applyFilters(search, value);
                        }}
                    >
                        <SelectTrigger className="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All statuses')}</SelectItem>
                            <SelectItem value="pending">{statusLabels.pending}</SelectItem>
                            <SelectItem value="approved">{statusLabels.approved}</SelectItem>
                            <SelectItem value="rejected">{statusLabels.rejected}</SelectItem>
                            <SelectItem value="suspended">{statusLabels.suspended}</SelectItem>
                            <SelectItem value="none">{t('Not started')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead>{t('Contact')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Availability')}</TableHead>
                                <TableHead>{t('Joined')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {providers.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        {t('No providers found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {providers.data.map((provider) => (
                                <TableRow key={provider.id}>
                                    <TableCell className="font-medium">{provider.name}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {provider.email}
                                        {provider.phone !== null && <span className="block text-xs">{provider.phone}</span>}
                                    </TableCell>
                                    <TableCell>
                                        {provider.approval_status === null ? (
                                            <Badge variant="outline">{t('Not started')}</Badge>
                                        ) : (
                                            <ApprovalStatusBadge status={provider.approval_status} />
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {provider.is_online ? (
                                            <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                                {t('Online')}
                                            </span>
                                        ) : (
                                            t('Offline')
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{provider.joined_at}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('View provider')}>
                                                <Link href={route('admin.providers.show', provider.id)}>
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={providers.meta} links={providers.links} />
            </div>
        </AdminLayout>
    );
}
