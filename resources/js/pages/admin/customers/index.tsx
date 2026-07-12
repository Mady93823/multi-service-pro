import { Pagination } from '@/components/catalog/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ALL = 'all';

interface CustomerRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    bookings_count: number;
    spent_total: number;
    wallet_balance: number;
    joined_at: string | null;
}

interface AdminCustomersIndexProps {
    customers: Paginated<CustomerRow>;
    filters: { search: string; status: string };
}

export default function AdminCustomersIndex({ customers, filters }: AdminCustomersIndexProps) {
    const t = useTrans();
    const money = useMoney();
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status !== '' ? filters.status : ALL);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Customers'), href: '/admin/customers' },
    ];

    const applyFilters = (nextSearch: string, nextStatus: string) => {
        router.get(
            route('admin.customers.index'),
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
            <Head title={t('Customers')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Customers')}</h1>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search by name, email or phone...')}
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
                        <SelectTrigger className="w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All customers')}</SelectItem>
                            <SelectItem value="active">{t('Active')}</SelectItem>
                            <SelectItem value="blocked">{t('Blocked')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Name')}</TableHead>
                                <TableHead>{t('Contact')}</TableHead>
                                <TableHead className="text-right">{t('Bookings')}</TableHead>
                                <TableHead className="text-right">{t('Spent')}</TableHead>
                                <TableHead className="text-right">{t('Wallet')}</TableHead>
                                <TableHead>{t('Joined')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                        {t('No customers found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {customers.data.map((customer) => (
                                <TableRow key={customer.id}>
                                    <TableCell className="font-medium">
                                        {customer.name}
                                        {!customer.is_active && (
                                            <Badge variant="destructive" className="ml-2">
                                                {t('Blocked')}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {customer.email}
                                        {customer.phone !== null && <span className="block text-xs">{customer.phone}</span>}
                                    </TableCell>
                                    <TableCell className="text-right">{customer.bookings_count}</TableCell>
                                    <TableCell className="text-right">{money(customer.spent_total)}</TableCell>
                                    <TableCell className="text-right">{money(customer.wallet_balance)}</TableCell>
                                    <TableCell className="text-muted-foreground">{customer.joined_at}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('View customer')}>
                                                <Link href={route('admin.customers.show', customer.id)}>
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

                <Pagination meta={customers.meta} links={customers.links} />
            </div>
        </AdminLayout>
    );
}
