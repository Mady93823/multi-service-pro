import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type AdminPayoutRow, type BreadcrumbItem, type NativePaginated, type PayoutMethodDetails, type PayoutStatus } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Ban, Banknote } from 'lucide-react';
import { useState } from 'react';

interface AdminPayoutsProps {
    payouts: NativePaginated<AdminPayoutRow>;
    filters: { status: string };
    statuses: PayoutStatus[];
}

const statusStyles: Record<PayoutStatus, string> = {
    requested: 'text-amber-700 dark:text-amber-300',
    approved: 'text-sky-700 dark:text-sky-400',
    paid: 'text-emerald-700 dark:text-emerald-400',
    rejected: 'text-red-700 dark:text-red-400',
};

/** UPI ids and bank rows have different keys — render whichever arrived. */
function methodSummary(details: PayoutMethodDetails): string {
    if (details.method === 'upi') {
        return details.upi_id ?? '';
    }

    return [details.account_name, details.account_number, details.ifsc].filter(Boolean).join(' · ');
}

export default function AdminPayouts({ payouts, filters, statuses }: AdminPayoutsProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Payouts'), href: '/admin/payouts' },
    ];

    const statusLabels: Record<PayoutStatus, string> = {
        requested: t('Requested'),
        approved: t('Approved'),
        paid: t('Paid'),
        rejected: t('Rejected'),
    };

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

    const filterBy = (status: string) => {
        router.get(route('admin.payouts.index'), status === '' ? {} : { status }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Payouts')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('Payouts')}</h1>
                    <div className="flex flex-wrap gap-1">
                        <Button size="sm" variant={filters.status === '' ? 'default' : 'outline'} onClick={() => filterBy('')}>
                            {t('All')}
                        </Button>
                        {statuses.map((status) => (
                            <Button
                                key={status}
                                size="sm"
                                variant={filters.status === status ? 'default' : 'outline'}
                                onClick={() => filterBy(status)}
                            >
                                {statusLabels[status]}
                            </Button>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {payouts.data.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center text-sm">{t('No payout requests to show.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Professional')}</TableHead>
                                        <TableHead>{t('Payout method')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Amount')}</TableHead>
                                        <TableHead className="text-right">{t('Actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payouts.data.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell>
                                                <span className="font-medium">{payout.provider.name ?? '—'}</span>
                                                <span className="text-muted-foreground block text-xs">
                                                    {payout.created_at !== null && dateFormat.format(new Date(payout.created_at))}
                                                    {payout.earnings_count !== null && ` · ${t(':count jobs', { count: payout.earnings_count })}`}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <span className="uppercase">{payout.method_details.method}</span>
                                                <span className="text-muted-foreground block text-xs">{methodSummary(payout.method_details)}</span>
                                                {/* The snapshot above is what was claimed; the tick below says
                                                    whether anyone ever checked the account it came from (M22). */}
                                                {payout.account !== null &&
                                                    (payout.account.is_verified ? (
                                                        <span className="text-xs text-emerald-700 dark:text-emerald-400">{t('Verified')}</span>
                                                    ) : (
                                                        <Button
                                                            variant="link"
                                                            size="sm"
                                                            className="h-auto p-0 text-xs"
                                                            onClick={() =>
                                                                router.post(
                                                                    route('admin.payout-accounts.verify', payout.account!.id),
                                                                    { verified: true },
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {t('Mark account verified')}
                                                        </Button>
                                                    ))}
                                            </TableCell>
                                            <TableCell className={`text-sm ${statusStyles[payout.status]}`}>
                                                {statusLabels[payout.status]}
                                                {payout.processed_by !== null && (
                                                    <span className="text-muted-foreground block text-xs">{payout.processed_by}</span>
                                                )}
                                                {payout.reference !== null && (
                                                    <span className="text-muted-foreground block text-xs">
                                                        {t('Reference')}: {payout.reference}
                                                    </span>
                                                )}
                                                {payout.note !== null && <span className="text-muted-foreground block text-xs">{payout.note}</span>}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">{money(payout.amount)}</TableCell>
                                            <TableCell className="text-right">
                                                <PayoutActions payout={payout} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {(payouts.prev_page_url !== null || payouts.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {payouts.prev_page_url !== null ? (
                            <Link href={payouts.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {payouts.next_page_url !== null && (
                            <Link href={payouts.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function PayoutActions({ payout }: { payout: AdminPayoutRow }) {
    const t = useTrans();
    const money = useMoney();
    const [payOpen, setPayOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);

    const pay = useForm({ reference: '' });
    const reject = useForm({ note: '' });

    // Only an open request can still be decided.
    if (payout.status === 'paid' || payout.status === 'rejected') {
        return <span className="text-muted-foreground text-xs">{t('Closed')}</span>;
    }

    return (
        <div className="flex justify-end gap-1">
            {payout.status === 'requested' && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => router.post(route('admin.payouts.approve', payout.id), {}, { preserveScroll: true })}
                >
                    <BadgeCheck className="mr-1 h-4 w-4" />
                    {t('Approve')}
                </Button>
            )}

            <Dialog open={payOpen} onOpenChange={setPayOpen}>
                <DialogTrigger asChild>
                    <Button size="sm">
                        <Banknote className="mr-1 h-4 w-4" />
                        {t('Mark paid')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Mark this payout as paid?')}</DialogTitle>
                        <DialogDescription>
                            {t('Transfer :amount to the professional first, then record the reference here.', {
                                amount: money(payout.amount),
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`reference-${payout.id}`}>{t('Transfer reference')}</Label>
                        <Input
                            id={`reference-${payout.id}`}
                            value={pay.data.reference}
                            onChange={(event) => pay.setData('reference', event.target.value)}
                        />
                        <InputError message={pay.errors.reference} />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPayOpen(false)} disabled={pay.processing}>
                            {t('Cancel')}
                        </Button>
                        <Button
                            disabled={pay.processing}
                            onClick={() =>
                                pay.post(route('admin.payouts.pay', payout.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setPayOpen(false),
                                })
                            }
                        >
                            {t('Mark paid')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <Ban className="mr-1 h-4 w-4" />
                        {t('Reject')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Reject this payout?')}</DialogTitle>
                        <DialogDescription>{t('The earnings go back to the professional’s available balance.')}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`note-${payout.id}`}>{t('Reason')}</Label>
                        <Textarea
                            id={`note-${payout.id}`}
                            value={reject.data.note}
                            onChange={(event) => reject.setData('note', event.target.value)}
                        />
                        <InputError message={reject.errors.note} />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setRejectOpen(false)} disabled={reject.processing}>
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={reject.processing}
                            onClick={() =>
                                reject.post(route('admin.payouts.reject', payout.id), {
                                    preserveScroll: true,
                                    onSuccess: () => setRejectOpen(false),
                                })
                            }
                        >
                            {t('Reject')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
