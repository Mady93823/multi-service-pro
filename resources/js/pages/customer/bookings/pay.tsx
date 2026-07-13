import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { postJson } from '@/lib/http';
import { useTrans } from '@/lib/i18n';
import {
    type Booking,
    type BreadcrumbItem,
    type PayMethods,
    type PaymentProvider,
    type RazorpaySession,
    type SharedData,
    type StripeSession,
} from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Landmark, LoaderCircle, Timer, Wallet } from 'lucide-react';
import { FormEventHandler, useCallback, useEffect, useState } from 'react';

const RAZORPAY_CHECKOUT = 'https://checkout.razorpay.com/v1/checkout.js';

interface RazorpayHandlerResponse {
    razorpay_order_id: string;
    razorpay_payment_id: string;
    razorpay_signature: string;
}

interface RazorpayOptions {
    key: string;
    amount: number;
    currency: string;
    order_id: string;
    name: string;
    description: string;
    prefill: { name: string; email: string };
    theme: { color?: string };
    handler: (response: RazorpayHandlerResponse) => void;
    modal: { ondismiss: () => void };
}

interface RazorpayInstance {
    open: () => void;
}

declare global {
    interface Window {
        Razorpay?: new (options: RazorpayOptions) => RazorpayInstance;
    }
}

interface PendingOffline {
    id: number;
    reference: string | null;
    submitted_at: string | null;
}

interface PayPageProps {
    booking: Booking;
    methods: PayMethods;
    pending_offline: PendingOffline | null;
    expires_at: string | null;
}

/** Load a third-party script once; resolve when it is ready to use. */
function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`) !== null) {
            resolve();

            return;
        }

        const element = document.createElement('script');
        element.src = src;
        element.async = true;
        element.onload = () => resolve();
        element.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(element);
    });
}

/** Whole minutes and seconds left, floored at zero. */
function secondsUntil(iso: string | null): number {
    if (iso === null) {
        return 0;
    }

    return Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000));
}

export default function PayPage({ booking, methods, pending_offline: pendingOffline, expires_at: expiresAt }: PayPageProps) {
    const t = useTrans();
    const money = useMoney();
    const { auth, branding, name: appName } = usePage<SharedData>().props;

    const [busy, setBusy] = useState<PaymentProvider | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [remaining, setRemaining] = useState(() => secondsUntil(expiresAt));

    const expired = expiresAt !== null && remaining === 0;

    useEffect(() => {
        if (expiresAt === null) {
            return;
        }

        const timer = window.setInterval(() => setRemaining(secondsUntil(expiresAt)), 1000);

        return () => window.clearInterval(timer);
    }, [expiresAt]);

    const failed = useCallback((message: string) => {
        setBusy(null);
        setError(message);
    }, []);

    const payWithRazorpay = useCallback(async () => {
        setBusy('razorpay');
        setError(null);

        try {
            await loadScript(RAZORPAY_CHECKOUT);

            const { session } = await postJson<{ session: RazorpaySession }>(route('payments.initiate', [booking.id, 'razorpay']));

            const Razorpay = window.Razorpay;

            if (Razorpay === undefined) {
                failed(t('The payment window could not be opened. Please try again.'));

                return;
            }

            new Razorpay({
                key: session.key_id,
                amount: session.amount,
                currency: session.currency,
                order_id: session.order_id,
                name: appName,
                description: booking.code,
                prefill: { name: auth.user.name, email: auth.user.email },
                theme: { color: branding.primary_color ?? undefined },
                // Signature proof — the server re-verifies the HMAC before it
                // marks anything paid; a tampered response is rejected there.
                handler: (response) => {
                    void postJson<{ redirect: string }>(route('payments.razorpay.callback', booking.id), response)
                        .then(({ redirect }) => router.visit(redirect))
                        .catch(() => failed(t('The payment could not be verified.')));
                },
                modal: { ondismiss: () => setBusy(null) },
            }).open();
        } catch {
            failed(t('The payment could not be started. Please try again.'));
        }
    }, [appName, auth.user, booking.code, booking.id, branding.primary_color, failed, t]);

    const payWithStripe = useCallback(async () => {
        setBusy('stripe');
        setError(null);

        try {
            const { session } = await postJson<{ session: StripeSession }>(route('payments.initiate', [booking.id, 'stripe']));

            window.location.href = session.url;
        } catch {
            failed(t('The payment could not be started. Please try again.'));
        }
    }, [booking.id, failed, t]);

    const payWithWallet = () => {
        setBusy('wallet');
        setError(null);
        router.post(route('payments.wallet', booking.id), {}, { onError: () => failed(t('The payment could not be started. Please try again.')) });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My bookings'), href: '/bookings' },
        { title: booking.code, href: `/bookings/${booking.id}` },
        { title: t('Payment'), href: `/bookings/${booking.id}/pay` },
    ];

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Complete payment')} />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{t('Complete payment')}</h1>
                    <p className="text-muted-foreground text-sm">
                        {booking.code} · {booking.scheduled_label} · {booking.slot_label}
                    </p>
                </div>

                {expired ? (
                    <p className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        <AlertTriangle className="h-4 w-4 shrink-0" />
                        {t('This booking has expired. Please book again.')}
                    </p>
                ) : (
                    expiresAt !== null && (
                        <p className="text-muted-foreground flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                            <Timer className="h-4 w-4 shrink-0" />
                            {t('Complete payment within :time or this booking is released.', {
                                time: `${minutes}:${String(seconds).padStart(2, '0')}`,
                            })}
                        </p>
                    )
                )}

                {error !== null && (
                    <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        {error}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Order summary')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {booking.items?.map((item) => (
                            <div key={item.id} className="flex justify-between gap-2">
                                <span className="text-muted-foreground min-w-0">
                                    {item.name} × {item.qty}
                                </span>
                                <span className="shrink-0">{money(item.line_total)}</span>
                            </div>
                        ))}
                        <Separator />
                        <div className="flex justify-between text-base font-semibold">
                            <span>{t('Amount due')}</span>
                            <span>{money(booking.total)}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Choose how to pay')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {methods.gateways.includes('razorpay') && (
                            <Button
                                className="w-full justify-start"
                                size="lg"
                                disabled={busy !== null || expired}
                                onClick={() => void payWithRazorpay()}
                            >
                                {busy === 'razorpay' ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <CreditCard className="h-4 w-4" />}
                                {t('Pay online — UPI, cards, net banking')}
                            </Button>
                        )}

                        {methods.gateways.includes('stripe') && (
                            <Button
                                variant="outline"
                                className="w-full justify-start"
                                size="lg"
                                disabled={busy !== null || expired}
                                onClick={() => void payWithStripe()}
                            >
                                {busy === 'stripe' ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <CreditCard className="h-4 w-4" />}
                                {t('Pay by card')}
                            </Button>
                        )}

                        {methods.wallet.enabled && (
                            <div className="space-y-1">
                                <Button
                                    variant="outline"
                                    className="w-full justify-start"
                                    size="lg"
                                    disabled={busy !== null || expired || !methods.wallet.sufficient}
                                    onClick={payWithWallet}
                                >
                                    {busy === 'wallet' ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Wallet className="h-4 w-4" />}
                                    {t('Pay from wallet')}
                                </Button>
                                <p className="text-muted-foreground text-xs">
                                    {methods.wallet.sufficient
                                        ? t('Balance :balance', { balance: money(methods.wallet.balance) })
                                        : t('Balance :balance — not enough for this booking.', { balance: money(methods.wallet.balance) })}
                                </p>
                            </div>
                        )}

                        {methods.gateways.length === 0 && !methods.wallet.enabled && !methods.offline.enabled && (
                            <p className="text-muted-foreground text-sm">{t('No payment method is available right now.')}</p>
                        )}
                    </CardContent>
                </Card>

                {methods.offline.enabled &&
                    (pendingOffline !== null ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('We are checking your transfer')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p className="text-muted-foreground">
                                    {t('Your booking is confirmed as soon as we match the payment. You will be notified either way.')}
                                </p>
                                {pendingOffline.reference !== null && (
                                    <p className="text-muted-foreground text-xs">
                                        {t('Reference: :reference', { reference: pendingOffline.reference })}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <OfflineCard booking={booking} methods={methods} disabled={expired} />
                    ))}
            </div>
        </CustomerLayout>
    );
}

/**
 * Bank transfer (M22). The customer pays from their own bank app and tells us —
 * nothing here settles the booking; an admin verifies the transfer first.
 */
function OfflineCard({ booking, methods, disabled }: { booking: Booking; methods: PayMethods; disabled: boolean }) {
    const t = useTrans();
    const money = useMoney();
    const accounts = methods.offline.accounts;

    const { data, setData, post, processing, errors } = useForm<{
        bank_account_id: number;
        reference: string;
        proof: File | null;
    }>({
        bank_account_id: accounts[0]?.id ?? 0,
        reference: '',
        proof: null,
    });

    const account = accounts.find((candidate) => candidate.id === data.bank_account_id) ?? accounts[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('payments.offline', booking.id), { forceFormData: true, preserveScroll: true });
    };

    if (account === undefined) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('Pay by bank transfer')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {methods.offline.instructions !== '' && (
                    <p className="text-muted-foreground text-sm whitespace-pre-line">{methods.offline.instructions}</p>
                )}

                {accounts.length > 1 && (
                    <div className="grid gap-2">
                        <Label htmlFor="bank_account_id">{t('Transfer to')}</Label>
                        <select
                            id="bank_account_id"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={data.bank_account_id}
                            onChange={(e) => setData('bank_account_id', Number(e.target.value))}
                        >
                            {accounts.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <dl className="space-y-1 rounded-md border p-3 text-sm">
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">{t('Amount')}</dt>
                        <dd className="font-medium">{money(booking.total)}</dd>
                    </div>
                    {account.account_name !== null && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">{t('Account holder')}</dt>
                            <dd>{account.account_name}</dd>
                        </div>
                    )}
                    {account.account_number !== null && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">{t('Account number')}</dt>
                            <dd>{account.account_number}</dd>
                        </div>
                    )}
                    {account.ifsc !== null && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">{t('IFSC')}</dt>
                            <dd>{account.ifsc}</dd>
                        </div>
                    )}
                    {account.upi_id !== null && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">{t('UPI')}</dt>
                            <dd>{account.upi_id}</dd>
                        </div>
                    )}
                    {account.notes !== null && <p className="text-muted-foreground pt-2 text-xs">{account.notes}</p>}
                </dl>

                {account.qr_url !== null && <img src={account.qr_url} alt="" className="h-40 w-40 rounded-md border object-contain" />}

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="reference">{t('Transaction reference')}</Label>
                        <Input
                            id="reference"
                            value={data.reference}
                            onChange={(e) => setData('reference', e.target.value)}
                            placeholder={t('UTR / transaction id')}
                        />
                        <InputError message={errors.reference} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="proof">{t('Payment proof')}</Label>
                        <Input
                            id="proof"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            onChange={(e) => setData('proof', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.proof} />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing || disabled}>
                        {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Landmark className="h-4 w-4" />}
                        {t('I have transferred the money')}
                    </Button>
                    <p className="text-muted-foreground text-xs">
                        {t('Your booking is confirmed once we verify the transfer — usually within a few hours.')}
                    </p>
                </form>
            </CardContent>
        </Card>
    );
}
