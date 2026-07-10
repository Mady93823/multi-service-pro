import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ProviderLayout from '@/layouts/provider-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import {
    type BreadcrumbItem,
    type Earning,
    type EarningsSummary,
    type EarningStatus,
    type NativePaginated,
    type PayoutRequestRow,
    type PayoutStatus,
} from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Banknote, Clock, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';

interface EarningsPageProps {
    summary: EarningsSummary;
    entries: NativePaginated<Earning>;
    payouts: PayoutRequestRow[];
    payouts_enabled: boolean;
    minimum_payout: number;
    has_open_payout: boolean;
}

const payoutStatusStyles: Record<PayoutStatus, string> = {
    requested: 'text-amber-700 dark:text-amber-300',
    approved: 'text-sky-700 dark:text-sky-400',
    paid: 'text-emerald-700 dark:text-emerald-400',
    rejected: 'text-red-700 dark:text-red-400',
};

interface PayoutForm {
    method: 'upi' | 'bank';
    upi_id: string;
    account_name: string;
    account_number: string;
    ifsc: string;
    [key: string]: string;
}

export default function EarningsPage({ summary, entries, payouts, payouts_enabled, minimum_payout, has_open_payout }: EarningsPageProps) {
    const t = useTrans();
    const money = useMoney();
    const [open, setOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/provider/dashboard' },
        { title: t('Earnings'), href: '/provider/earnings' },
    ];

    const { data, setData, post, processing, errors, reset } = useForm<PayoutForm>({
        method: 'upi',
        upi_id: '',
        account_name: '',
        account_number: '',
        ifsc: '',
    });

    // A cash-only provider owes commission, so `claimable` can be negative —
    // the ledger carries the sign and the button stays shut.
    const owesMoney = summary.claimable < 0;
    const canRequest = payouts_enabled && !has_open_payout && summary.claimable >= minimum_payout && summary.claimable > 0;

    const statusLabels: Record<EarningStatus, string> = {
        pending: t('On hold'),
        available: t('Available'),
        paid_out: t('Paid out'),
    };

    const payoutStatusLabels: Record<PayoutStatus, string> = {
        requested: t('Requested'),
        approved: t('Approved'),
        paid: t('Paid'),
        rejected: t('Rejected'),
    };

    const typeLabels: Record<string, string> = {
        job: t('Completed job'),
        reversal: t('Refund reversal'),
        adjustment: t('Adjustment'),
    };

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

    const submit = () => {
        post(route('provider.payouts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    const requestBlockedReason = () => {
        if (!payouts_enabled) return t('Payouts are currently disabled.');
        if (has_open_payout) return t('You already have a payout request in progress.');
        if (owesMoney) return t('Commission on your cash jobs is deducted from your next payout.');
        if (summary.claimable <= 0) return t('You have no balance available to withdraw.');

        return t('Payouts start at :amount.', { amount: money(minimum_payout) });
    };

    return (
        <ProviderLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Earnings')} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Earnings')}</h1>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard
                        icon={<Wallet className="h-5 w-5" />}
                        label={owesMoney ? t('Commission owed') : t('Available to withdraw')}
                        value={money(Math.abs(summary.claimable))}
                        tone={owesMoney ? 'negative' : 'positive'}
                    />
                    <SummaryCard icon={<Clock className="h-5 w-5" />} label={t('On hold')} value={money(summary.pending)} />
                    <SummaryCard icon={<TrendingUp className="h-5 w-5" />} label={t('Lifetime earnings')} value={money(summary.net)} />
                    <SummaryCard icon={<Banknote className="h-5 w-5" />} label={t('Commission paid')} value={money(summary.commission)} />
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">{t('Payouts')}</CardTitle>
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button size="sm" disabled={!canRequest}>
                                    {t('Request payout')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>{t('Request a payout')}</DialogTitle>
                                    <DialogDescription>
                                        {t('Your whole available balance of :amount will be paid out.', {
                                            amount: money(summary.claimable),
                                        })}
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="method">{t('Payout method')}</Label>
                                        <Select value={data.method} onValueChange={(value) => setData('method', value as 'upi' | 'bank')}>
                                            <SelectTrigger id="method">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="upi">{t('UPI')}</SelectItem>
                                                <SelectItem value="bank">{t('Bank transfer')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {data.method === 'upi' ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor="upi_id">{t('UPI ID')}</Label>
                                            <Input
                                                id="upi_id"
                                                value={data.upi_id}
                                                autoComplete="off"
                                                onChange={(event) => setData('upi_id', event.target.value)}
                                            />
                                            <InputError message={errors.upi_id} />
                                        </div>
                                    ) : (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="account_name">{t('Account holder name')}</Label>
                                                <Input
                                                    id="account_name"
                                                    value={data.account_name}
                                                    onChange={(event) => setData('account_name', event.target.value)}
                                                />
                                                <InputError message={errors.account_name} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="account_number">{t('Account number')}</Label>
                                                <Input
                                                    id="account_number"
                                                    value={data.account_number}
                                                    autoComplete="off"
                                                    onChange={(event) => setData('account_number', event.target.value)}
                                                />
                                                <InputError message={errors.account_number} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="ifsc">{t('IFSC code')}</Label>
                                                <Input id="ifsc" value={data.ifsc} onChange={(event) => setData('ifsc', event.target.value)} />
                                                <InputError message={errors.ifsc} />
                                            </div>
                                        </>
                                    )}

                                    <InputError message={errors.payout} />
                                </div>

                                <DialogFooter>
                                    <Button variant="outline" onClick={() => setOpen(false)} disabled={processing}>
                                        {t('Cancel')}
                                    </Button>
                                    <Button onClick={submit} disabled={processing}>
                                        {t('Request payout')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <CardContent className="p-0">
                        {!canRequest && <p className="text-muted-foreground px-6 pb-4 text-sm">{requestBlockedReason()}</p>}

                        {payouts.length === 0 ? (
                            <p className="text-muted-foreground p-6 pt-0 text-center text-sm">{t('No payout requests yet.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Requested')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Amount')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payouts.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell>
                                                {payout.created_at !== null && dateFormat.format(new Date(payout.created_at))}
                                                {payout.reference !== null && (
                                                    <span className="text-muted-foreground block text-xs">
                                                        {t('Reference')}: {payout.reference}
                                                    </span>
                                                )}
                                                {payout.note !== null && <span className="text-muted-foreground block text-xs">{payout.note}</span>}
                                            </TableCell>
                                            <TableCell className={payoutStatusStyles[payout.status]}>{payoutStatusLabels[payout.status]}</TableCell>
                                            <TableCell className="text-right font-medium">{money(payout.amount)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Ledger')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {entries.data.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center text-sm">{t('Complete a job to start earning.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Job')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Job value')}</TableHead>
                                        <TableHead className="text-right">{t('Commission')}</TableHead>
                                        <TableHead className="text-right">{t('You earn')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => {
                                        const net = Number(entry.net);

                                        return (
                                            <TableRow key={entry.id}>
                                                <TableCell>
                                                    <span className="font-medium">{entry.booking_code ?? '—'}</span>
                                                    <span className="text-muted-foreground block text-xs">
                                                        {typeLabels[entry.type] ?? entry.type}
                                                        {entry.created_at !== null && ` · ${dateFormat.format(new Date(entry.created_at))}`}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">
                                                    {statusLabels[entry.status]}
                                                    {entry.status === 'pending' && entry.available_at !== null && (
                                                        <span className="block text-xs">
                                                            {t('Available :date', { date: dateFormat.format(new Date(entry.available_at)) })}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">{money(entry.gross)}</TableCell>
                                                <TableCell className="text-muted-foreground text-right">
                                                    {money(entry.commission)}
                                                    <span className="block text-xs">{entry.commission_rate}%</span>
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-medium ${net < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'}`}
                                                >
                                                    {money(net)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {(entries.prev_page_url !== null || entries.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {entries.prev_page_url !== null ? (
                            <Link href={entries.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {entries.next_page_url !== null && (
                            <Link href={entries.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </ProviderLayout>
    );
}

function SummaryCard({ icon, label, value, tone }: { icon: React.ReactNode; label: string; value: string; tone?: 'positive' | 'negative' }) {
    const toneClass = tone === 'negative' ? 'text-red-700 dark:text-red-400' : tone === 'positive' ? 'text-emerald-600 dark:text-emerald-400' : '';

    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-5">
                <span className="bg-muted text-muted-foreground flex h-10 w-10 items-center justify-center rounded-full">{icon}</span>
                <div>
                    <p className="text-muted-foreground text-xs">{label}</p>
                    <p className={`text-lg font-semibold ${toneClass}`}>{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}
