import { Pagination } from '@/components/catalog/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type AdminPayment, type BreadcrumbItem, type Paginated, type PaymentState } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Check, FileText, Search, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const ALL = 'all';

interface AdminPaymentsIndexProps {
    payments: Paginated<AdminPayment>;
    totals: { count: number; captured: number; refunded: number; awaiting: number };
    filters: { gateway: string; status: string; search: string; from: string; to: string };
    gateways: string[];
    statuses: string[];
}

const STATUS_VARIANT: Record<PaymentState, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    captured: 'default',
    initiated: 'secondary',
    failed: 'destructive',
    refunded: 'outline',
};

export default function AdminPaymentsIndex({ payments, totals, filters, gateways, statuses }: AdminPaymentsIndexProps) {
    const t = useTrans();
    const money = useMoney();

    const [search, setSearch] = useState(filters.search);
    const [rejecting, setRejecting] = useState<AdminPayment | null>(null);

    const rejectForm = useForm({ reason: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Payments'), href: '/admin/payments' },
    ];

    const gatewayLabels: Record<string, string> = {
        razorpay: t('Razorpay'),
        stripe: t('Stripe'),
        cash: t('Cash'),
        wallet: t('Wallet'),
        offline: t('Bank transfer'),
    };

    const statusLabels: Record<string, string> = {
        initiated: t('Awaiting'),
        captured: t('Captured'),
        failed: t('Failed'),
        refunded: t('Refunded'),
    };

    const apply = (next: Partial<AdminPaymentsIndexProps['filters']>) => {
        const merged = { ...filters, search, ...next };

        router.get(route('admin.payments.index'), Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== '')), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        apply({});
    };

    const verify = (payment: AdminPayment) => {
        router.post(route('admin.payments.verify', payment.id), {}, { preserveScroll: true });
    };

    const submitReject: FormEventHandler = (e) => {
        e.preventDefault();

        if (rejecting === null) {
            return;
        }

        rejectForm.post(route('admin.payments.reject', rejecting.id), {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                setRejecting(null);
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Payments')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Payments')}</h1>
                    <Button asChild variant="outline">
                        <Link href={route('admin.bank-accounts.index')}>{t('Bank accounts')}</Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs">{t('Payments (filtered)')}</p>
                        <p className="text-2xl font-semibold">{totals.count}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs">{t('Captured')}</p>
                        <p className="text-2xl font-semibold">{money(totals.captured)}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs">{t('Refunded')}</p>
                        <p className="text-2xl font-semibold">{money(totals.refunded)}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs">{t('Awaiting verification')}</p>
                        <p className="text-2xl font-semibold">{totals.awaiting}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={submitSearch} className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Booking code or reference...')}
                            className="w-64"
                        />
                        <Button type="submit" variant="outline" size="icon" aria-label={t('Search')}>
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>

                    <Select
                        value={filters.gateway !== '' ? filters.gateway : ALL}
                        onValueChange={(value) => apply({ gateway: value === ALL ? '' : value })}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All methods')}</SelectItem>
                            {gateways.map((gateway) => (
                                <SelectItem key={gateway} value={gateway}>
                                    {gatewayLabels[gateway] ?? gateway}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status !== '' ? filters.status : ALL}
                        onValueChange={(value) => apply({ status: value === ALL ? '' : value })}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All statuses')}</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {statusLabels[status] ?? status}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                        className="w-40"
                        aria-label={t('From')}
                    />
                    <Input type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} className="w-40" aria-label={t('To')} />
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Booking')}</TableHead>
                                <TableHead>{t('Method')}</TableHead>
                                <TableHead>{t('Reference')}</TableHead>
                                <TableHead className="text-right">{t('Amount')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Date')}</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center">
                                        {t('No payments found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {payments.data.map((payment) => {
                                const awaiting = payment.gateway === 'offline' && payment.status === 'initiated';

                                return (
                                    <TableRow key={payment.id}>
                                        <TableCell className="font-medium">
                                            <Link href={route('admin.bookings.show', payment.booking.id)} className="hover:underline">
                                                {payment.booking.code}
                                            </Link>
                                            <span className="text-muted-foreground block text-xs">{payment.booking.customer}</span>
                                        </TableCell>
                                        <TableCell>
                                            {gatewayLabels[payment.gateway] ?? payment.gateway}
                                            {payment.bank_account !== null && (
                                                <span className="text-muted-foreground block text-xs">{payment.bank_account}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground max-w-48 truncate text-xs">
                                            {payment.reference ?? '—'}
                                            {payment.failure_reason !== null && (
                                                <span className="text-destructive block">{payment.failure_reason}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">{money(payment.amount)}</TableCell>
                                        <TableCell>
                                            <Badge variant={STATUS_VARIANT[payment.status]}>{statusLabels[payment.status] ?? payment.status}</Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">{payment.captured_at ?? payment.created_at}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-1">
                                                {payment.proof_url !== null && (
                                                    <Button asChild variant="ghost" size="icon" aria-label={t('View proof')}>
                                                        <a href={payment.proof_url} target="_blank" rel="noopener noreferrer">
                                                            <FileText className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                                {awaiting && (
                                                    <>
                                                        <Button size="sm" onClick={() => verify(payment)}>
                                                            <Check className="h-4 w-4" />
                                                            {t('Verify')}
                                                        </Button>
                                                        <Dialog
                                                            open={rejecting?.id === payment.id}
                                                            onOpenChange={(open) => setRejecting(open ? payment : null)}
                                                        >
                                                            <DialogTrigger asChild>
                                                                <Button variant="outline" size="sm">
                                                                    <X className="h-4 w-4" />
                                                                    {t('Reject')}
                                                                </Button>
                                                            </DialogTrigger>
                                                            <DialogContent>
                                                                <form onSubmit={submitReject}>
                                                                    <DialogHeader>
                                                                        <DialogTitle>{t('Reject this payment')}</DialogTitle>
                                                                        <DialogDescription>
                                                                            {t(
                                                                                'The customer is told why, and the booking stays awaiting payment — they can pay again.',
                                                                            )}
                                                                        </DialogDescription>
                                                                    </DialogHeader>
                                                                    <div className="grid gap-2 py-4">
                                                                        <Label htmlFor="reason">{t('Reason')}</Label>
                                                                        <Textarea
                                                                            id="reason"
                                                                            value={rejectForm.data.reason}
                                                                            onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                                                            placeholder={t('We could not find this transfer in our account.')}
                                                                        />
                                                                        <InputError message={rejectForm.errors.reason} />
                                                                    </div>
                                                                    <DialogFooter>
                                                                        <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                                                                            {t('Reject payment')}
                                                                        </Button>
                                                                    </DialogFooter>
                                                                </form>
                                                            </DialogContent>
                                                        </Dialog>
                                                    </>
                                                )}
                                                {payment.status === 'captured' && payment.gateway === 'offline' && (
                                                    <BadgeCheck className="text-muted-foreground h-4 w-4" aria-label={t('Verified')} />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                <Pagination meta={payments.meta} links={payments.links} />
            </div>
        </AdminLayout>
    );
}
