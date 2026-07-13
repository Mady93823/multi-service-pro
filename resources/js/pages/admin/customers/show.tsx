import { BookingStatusBadge } from '@/components/booking/status-badge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BookingStatus, type BreadcrumbItem, type TicketStatus } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, ShieldCheck, UserRoundSearch, Wallet as WalletIcon } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CustomerDetail {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    blocked_reason: string | null;
    referral_code: string | null;
    joined_at: string | null;
}

interface CustomerStats {
    bookings: number;
    completed: number;
    spent_total: number;
    wallet_balance: number;
    referrals: number;
    tickets: number;
}

interface BookingRow {
    id: number;
    code: string;
    status: BookingStatus;
    total: number;
    scheduled_at: string | null;
}

interface TransactionRow {
    id: number;
    type: string;
    direction: string;
    amount: number;
    balance_after: number;
    created_at: string | null;
}

interface TicketRow {
    id: number;
    code: string;
    subject: string;
    status: TicketStatus;
    status_label: string;
}

interface AdminCustomerShowProps {
    customer: CustomerDetail;
    stats: CustomerStats;
    bookings: BookingRow[];
    transactions: TransactionRow[];
    tickets: TicketRow[];
}

export default function AdminCustomerShow({ customer, stats, bookings, transactions, tickets }: AdminCustomerShowProps) {
    const t = useTrans();
    const money = useMoney();
    const [blockOpen, setBlockOpen] = useState(false);

    const blockForm = useForm({ reason: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Customers'), href: '/admin/customers' },
        { title: customer.name, href: `/admin/customers/${customer.id}` },
    ];

    const submitBlock: FormEventHandler = (e) => {
        e.preventDefault();
        blockForm.post(route('admin.customers.block', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                blockForm.reset();
                setBlockOpen(false);
            },
        });
    };

    const tiles: { label: string; value: string }[] = [
        { label: t('Bookings'), value: String(stats.bookings) },
        { label: t('Completed'), value: String(stats.completed) },
        { label: t('Lifetime spend'), value: money(stats.spent_total) },
        { label: t('Wallet balance'), value: money(stats.wallet_balance) },
        { label: t('Referrals'), value: String(stats.referrals) },
        { label: t('Tickets'), value: String(stats.tickets) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={customer.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {customer.name}
                            {!customer.is_active && (
                                <Badge variant="destructive" className="ml-2 align-middle">
                                    {t('Blocked')}
                                </Badge>
                            )}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {customer.email}
                            {customer.phone !== null && <span> · {customer.phone}</span>}
                            {customer.joined_at !== null && (
                                <span>
                                    {' '}
                                    · {t('Joined')} {customer.joined_at}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {customer.is_active && (
                            <Button variant="outline" size="sm" onClick={() => router.post(route('admin.impersonate.store', customer.id))}>
                                <UserRoundSearch aria-hidden />
                                {t('Login as customer')}
                            </Button>
                        )}

                        {customer.is_active ? (
                            <Dialog open={blockOpen} onOpenChange={setBlockOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        <Ban aria-hidden />
                                        {t('Block')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <form onSubmit={submitBlock}>
                                        <DialogHeader>
                                            <DialogTitle>{t('Block this customer')}</DialogTitle>
                                            <DialogDescription>
                                                {t('They are signed out immediately and cannot sign in again until unblocked.')}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="grid gap-2 py-4">
                                            <Label htmlFor="reason">{t('Reason')}</Label>
                                            <Input
                                                id="reason"
                                                value={blockForm.data.reason}
                                                onChange={(e) => blockForm.setData('reason', e.target.value)}
                                                required
                                            />
                                            <InputError message={blockForm.errors.reason} />
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" variant="destructive" disabled={blockForm.processing}>
                                                {t('Block customer')}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : (
                            <Button variant="outline" size="sm" onClick={() => router.post(route('admin.customers.unblock', customer.id))}>
                                <ShieldCheck aria-hidden />
                                {t('Unblock')}
                            </Button>
                        )}
                    </div>
                </div>

                {!customer.is_active && customer.blocked_reason !== null && (
                    <Card className="border-destructive/40">
                        <CardContent className="pt-6 text-sm">
                            <span className="font-medium">{t('Blocked reason')}: </span>
                            {customer.blocked_reason}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-xs">{tile.label}</p>
                                <p className="text-lg font-semibold">{tile.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Recent bookings')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Booking')}</TableHead>
                                        <TableHead>{t('Status')}</TableHead>
                                        <TableHead className="text-right">{t('Total')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bookings.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={3} className="text-muted-foreground py-6 text-center">
                                                {t('No bookings yet.')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {bookings.map((booking) => (
                                        <TableRow key={booking.id}>
                                            <TableCell>
                                                <Link href={route('admin.bookings.show', booking.id)} className="font-medium hover:underline">
                                                    {booking.code}
                                                </Link>
                                                <span className="text-muted-foreground block text-xs">{booking.scheduled_at}</span>
                                            </TableCell>
                                            <TableCell>
                                                <BookingStatusBadge status={booking.status} />
                                            </TableCell>
                                            <TableCell className="text-right">{money(booking.total)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle>{t('Wallet')}</CardTitle>
                            <AdjustWalletDialog customerId={customer.id} />
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Entry')}</TableHead>
                                        <TableHead className="text-right">{t('Amount')}</TableHead>
                                        <TableHead className="text-right">{t('Balance')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={3} className="text-muted-foreground py-6 text-center">
                                                {t('No wallet activity.')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {transactions.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell>
                                                {transaction.type}
                                                <span className="text-muted-foreground block text-xs">{transaction.created_at}</span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {transaction.direction === 'debit' ? '−' : '+'}
                                                {money(transaction.amount)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-right">{money(transaction.balance_after)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Support tickets')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Ticket')}</TableHead>
                                    <TableHead>{t('Subject')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tickets.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-muted-foreground py-6 text-center">
                                            {t('No tickets raised.')}
                                        </TableCell>
                                    </TableRow>
                                )}
                                {tickets.map((ticket) => (
                                    <TableRow key={ticket.id}>
                                        <TableCell className="font-mono text-xs">
                                            <Link href={route('admin.tickets.show', ticket.id)} className="hover:underline">
                                                {ticket.code}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{ticket.subject}</TableCell>
                                        <TableCell>
                                            <Badge variant={ticket.status === 'closed' ? 'secondary' : 'outline'}>{ticket.status_label}</Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

/**
 * Manual wallet correction (M22). Goes through WalletService like every other
 * movement, so an overdraw is refused and the ledger still reconciles — and the
 * reason lands on the entry.
 */
function AdjustWalletDialog({ customerId }: { customerId: number }) {
    const t = useTrans();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        direction: 'credit' | 'debit';
        amount: string;
        reason: string;
    }>({
        direction: 'credit',
        amount: '',
        reason: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('admin.customers.wallet', customerId), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <WalletIcon className="h-4 w-4" />
                    {t('Adjust')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{t('Adjust wallet')}</DialogTitle>
                        <DialogDescription>{t('The entry is added to the ledger with your reason and your name.')}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="direction">{t('Direction')}</Label>
                            <Select value={data.direction} onValueChange={(value) => setData('direction', value as 'credit' | 'debit')}>
                                <SelectTrigger id="direction">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="credit">{t('Credit (add money)')}</SelectItem>
                                    <SelectItem value="debit">{t('Debit (take money back)')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="amount">{t('Amount')}</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reason">{t('Reason')}</Label>
                            <Input
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                placeholder={t('Goodwill credit for a late job')}
                                required
                            />
                            <InputError message={errors.reason} />
                        </div>

                        {/* A debit that would overdraw the wallet is refused by WalletService. */}
                        <InputError message={(errors as Record<string, string | undefined>).wallet} />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {t('Apply')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
