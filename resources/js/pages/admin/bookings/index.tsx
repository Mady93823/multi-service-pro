import { BookingStatusBadge, useBookingStatusLabels } from '@/components/booking/status-badge';
import { Pagination } from '@/components/catalog/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BookingStatus, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ALL = 'all';

interface AdminBookingsIndexProps {
    bookings: Paginated<Booking>;
    filters: { status: string; search: string };
    statuses: BookingStatus[];
}

export default function AdminBookingsIndex({ bookings, filters, statuses }: AdminBookingsIndexProps) {
    const t = useTrans();
    const money = useMoney();
    const statusLabels = useBookingStatusLabels();
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status !== '' ? filters.status : ALL);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Bookings'), href: '/admin/bookings' },
    ];

    const applyFilters = (nextSearch: string, nextStatus: string) => {
        router.get(
            route('admin.bookings.index'),
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
            <Head title={t('Bookings')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Bookings')}</h1>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search by code or customer...')}
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
                            {statuses.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {statusLabels[value]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Code')}</TableHead>
                                <TableHead>{t('Customer')}</TableHead>
                                <TableHead>{t('Scheduled')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Professional')}</TableHead>
                                <TableHead className="text-right">{t('Total')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bookings.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                        {t('No bookings found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {bookings.data.map((booking) => (
                                <TableRow key={booking.id}>
                                    <TableCell className="font-medium">{booking.code}</TableCell>
                                    <TableCell className="text-muted-foreground">{booking.customer?.name}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {booking.scheduled_label}
                                        <span className="block text-xs">{booking.slot_label}</span>
                                    </TableCell>
                                    <TableCell>
                                        <BookingStatusBadge status={booking.status} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{booking.provider?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right">{money(booking.total)}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button asChild variant="ghost" size="icon" aria-label={t('View booking')}>
                                                <Link href={route('admin.bookings.show', booking.id)}>
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

                <Pagination meta={bookings.meta} links={bookings.links} />
            </div>
        </AdminLayout>
    );
}
