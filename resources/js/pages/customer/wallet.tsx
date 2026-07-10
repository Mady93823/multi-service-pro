import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NativePaginated, type ReferralCard, type WalletTransaction } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Check, Copy, Gift, Wallet } from 'lucide-react';
import { useState } from 'react';

interface WalletPageProps {
    balance: string;
    transactions: NativePaginated<WalletTransaction>;
    referrals: ReferralCard | null;
}

export default function WalletPage({ balance, transactions, referrals }: WalletPageProps) {
    const t = useTrans();
    const money = useMoney();
    const [copied, setCopied] = useState(false);

    const copyShareUrl = () => {
        if (referrals === null) {
            return;
        }

        void navigator.clipboard.writeText(referrals.share_url).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('Wallet'), href: '/wallet' },
    ];

    // Ledger `type` is an open string column — fall back to the raw value so a
    // new movement type never renders as a blank cell.
    const typeLabels: Record<string, string> = {
        payment: t('Booking payment'),
        refund: t('Refund'),
        referral_reward: t('Referral reward'),
    };

    const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Wallet')} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Wallet')}</h1>

                <Card>
                    <CardContent className="flex items-center gap-4 p-6">
                        <span className="bg-primary/10 text-primary flex h-12 w-12 items-center justify-center rounded-full">
                            <Wallet className="h-6 w-6" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-sm">{t('Available balance')}</p>
                            <p className="text-2xl font-semibold">{money(balance)}</p>
                        </div>
                    </CardContent>
                </Card>

                {referrals !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Gift className="h-4 w-4" />
                                {t('Refer & earn')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                {t('Share your code — when a friend completes their first booking, you earn :amount in wallet credit.', {
                                    amount: money(referrals.reward_amount),
                                })}
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="bg-muted rounded-lg px-3 py-2 font-mono text-sm font-semibold tracking-wider">{referrals.code}</span>
                                <Button type="button" variant="outline" size="sm" onClick={copyShareUrl}>
                                    {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
                                    {copied ? t('Copied!') : t('Copy invite link')}
                                </Button>
                            </div>
                            {referrals.entries.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">{t('Your referrals')}</p>
                                    {referrals.entries.map((entry) => (
                                        <div key={entry.id} className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {entry.referee_name ?? t('A friend')}
                                                {entry.created_at !== null && <span className="ml-2 text-xs">{entry.created_at}</span>}
                                            </span>
                                            {entry.status === 'rewarded' ? (
                                                <Badge className="bg-emerald-600 text-white">
                                                    {entry.reward_amount !== null ? `+ ${money(entry.reward_amount)}` : t('Rewarded')}
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">{t('Waiting for first booking')}</Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Transactions')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {transactions.data.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center text-sm">{t('No wallet activity yet.')}</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Description')}</TableHead>
                                        <TableHead className="text-right">{t('Amount')}</TableHead>
                                        <TableHead className="text-right">{t('Balance')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.data.map((txn) => {
                                        const credit = txn.direction === 'credit';

                                        return (
                                            <TableRow key={txn.id}>
                                                <TableCell>
                                                    <span className="flex items-center gap-2 font-medium">
                                                        {credit ? (
                                                            <ArrowDownLeft className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                                        ) : (
                                                            <ArrowUpRight className="text-muted-foreground h-4 w-4" />
                                                        )}
                                                        {typeLabels[txn.type] ?? txn.type}
                                                    </span>
                                                    <span className="text-muted-foreground block text-xs">
                                                        {dateFormat.format(new Date(txn.created_at))}
                                                        {txn.note !== null && ` · ${txn.note}`}
                                                    </span>
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-medium ${credit ? 'text-emerald-600 dark:text-emerald-400' : ''}`}
                                                >
                                                    {credit ? '+' : '−'}
                                                    {money(txn.amount)}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-right">{money(txn.balance_after)}</TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {(transactions.prev_page_url !== null || transactions.next_page_url !== null) && (
                    <div className="flex items-center justify-between text-sm">
                        {transactions.prev_page_url !== null ? (
                            <Link href={transactions.prev_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {transactions.next_page_url !== null && (
                            <Link href={transactions.next_page_url} preserveScroll className="text-primary hover:underline">
                                {t('Next')}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </CustomerLayout>
    );
}
