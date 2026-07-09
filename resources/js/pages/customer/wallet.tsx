import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BreadcrumbItem, type NativePaginated, type WalletTransaction } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Wallet } from 'lucide-react';

interface WalletPageProps {
    balance: string;
    transactions: NativePaginated<WalletTransaction>;
}

export default function WalletPage({ balance, transactions }: WalletPageProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('Wallet'), href: '/wallet' },
    ];

    // Ledger `type` is an open string column — fall back to the raw value so a
    // new movement type never renders as a blank cell.
    const typeLabels: Record<string, string> = {
        payment: t('Booking payment'),
        refund: t('Refund'),
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
