import { SlotPicker } from '@/components/booking/slot-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Address, type CartSummary, type SlotDay } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Banknote, CreditCard, ImagePlus, LoaderCircle, MapPin, TicketPercent, Wallet, X } from 'lucide-react';
import { FormEventHandler, type ComponentType } from 'react';

interface CheckoutLine {
    key: string;
    qty: number;
    name: string;
    addon_names: string[];
    line_total: string;
}

interface CheckoutAddress {
    address: Address;
    blocked_services: string[];
}

interface CheckoutPageProps {
    lines: CheckoutLine[];
    addresses: CheckoutAddress[];
    slot_days: SlotDay[];
    summary: CartSummary;
    payment_methods: string[];
    wallet_balance: string;
    coupon: { code: string; discount: string } | null;
    coupon_error: string | null;
}

type CheckoutForm = {
    address_id: number | null;
    scheduled_at: string | null;
    payment_method: string;
    notes: string;
    photos: File[];
};

export default function CheckoutPage({
    lines,
    addresses,
    slot_days: slotDays,
    summary,
    payment_methods: paymentMethods,
    wallet_balance: walletBalance,
    coupon,
    coupon_error: couponError,
}: CheckoutPageProps) {
    const t = useTrans();
    const money = useMoney();

    const couponForm = useForm<{ coupon: string }>({ coupon: '' });

    const applyCoupon = () => {
        couponForm.post(route('checkout.coupon.store'), {
            preserveScroll: true,
            onSuccess: () => couponForm.reset(),
        });
    };

    const removeCoupon = () => {
        router.delete(route('checkout.coupon.destroy'), { preserveScroll: true });
    };

    const defaultAddress = addresses.find((entry) => entry.address.is_default && entry.blocked_services.length === 0);

    // The server decides what is on offer (pay-after-service flag, which
    // gateways hold credentials, wallet flag) — never hardcode the list.
    const walletShort = Number(walletBalance) < Number(summary.total);
    const selectable = (method: string) => method !== 'wallet' || !walletShort;

    const { data, setData, post, processing, errors } = useForm<CheckoutForm>({
        address_id: defaultAddress?.address.id ?? null,
        scheduled_at: null,
        payment_method: paymentMethods.find(selectable) ?? paymentMethods[0] ?? 'cash',
        notes: '',
        photos: [],
    });

    const methodMeta: Record<string, { icon: ComponentType<{ className?: string }>; title: string; hint: string }> = {
        cash: {
            icon: Banknote,
            title: t('Pay after service'),
            hint: t('Pay in cash or UPI once the job is done.'),
        },
        razorpay: {
            icon: CreditCard,
            title: t('Pay online'),
            hint: t('UPI, cards, net banking — secured by Razorpay.'),
        },
        stripe: {
            icon: CreditCard,
            title: t('Pay by card'),
            hint: t('Secure card checkout by Stripe.'),
        },
        wallet: {
            icon: Wallet,
            title: t('Wallet'),
            hint: walletShort
                ? t('Balance :balance — not enough for this booking.', { balance: money(walletBalance) })
                : t('Balance :balance', { balance: money(walletBalance) }),
        },
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('checkout.store'), { forceFormData: true, preserveScroll: true });
    };

    // Gateways are the default branch: every method that is not cash or
    // wallet hands off to a hosted checkout.
    const labels: Record<string, string> = {
        cash: t('Place booking'),
        wallet: t('Pay from wallet'),
    };
    const hints: Record<string, string> = {
        cash: t('No charge until the service is completed.'),
        wallet: t('Paid instantly from your wallet balance.'),
    };

    const submitLabel = labels[data.payment_method] ?? t('Continue to payment');
    const submitHint = hints[data.payment_method] ?? t('You will be taken to a secure payment page.');

    const addressLabels: Record<Address['label'], string> = {
        home: t('Home'),
        work: t('Work'),
        other: t('Other'),
    };

    return (
        <PublicLayout>
            <Head title={t('Checkout')} />

            <h1 className="text-xl font-semibold">{t('Checkout')}</h1>

            <form onSubmit={submit} className="grid gap-8 py-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Service address')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {addresses.length === 0 && (
                                <div className="space-y-3">
                                    <p className="text-muted-foreground text-sm">{t('Add an address to continue.')}</p>
                                    <Button asChild variant="outline">
                                        <Link href={route('addresses.create')}>
                                            <MapPin className="h-4 w-4" />
                                            {t('Add address')}
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            {addresses.map(({ address, blocked_services: blocked }) => {
                                const isBlocked = blocked.length > 0;
                                const selected = data.address_id === address.id;

                                return (
                                    <button
                                        key={address.id}
                                        type="button"
                                        disabled={isBlocked}
                                        onClick={() => setData('address_id', address.id)}
                                        className={cn(
                                            'w-full rounded-lg border p-3 text-left text-sm transition-colors',
                                            selected && 'border-primary ring-primary ring-1',
                                            isBlocked && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {addressLabels[address.label]}
                                                {address.is_default && <span className="text-muted-foreground ml-2 text-xs">{t('Default')}</span>}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground mt-0.5">
                                            {address.line1}
                                            {address.line2 ? `, ${address.line2}` : ''} · {address.city} {address.postal_code}
                                        </p>
                                        {isBlocked && (
                                            <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                {t('Not available here: :services', { services: blocked.join(', ') })}
                                            </p>
                                        )}
                                    </button>
                                );
                            })}
                            {addresses.length > 0 && (
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={route('addresses.create')}>
                                        <MapPin className="h-4 w-4" />
                                        {t('Add another address')}
                                    </Link>
                                </Button>
                            )}
                            <InputError message={errors.address_id} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Pick a time')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SlotPicker days={slotDays} value={data.scheduled_at} onChange={(value) => setData('scheduled_at', value)} />
                            <InputError message={errors.scheduled_at} className="mt-2" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Details for the professional')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="notes">{t('Notes (optional)')}</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    rows={3}
                                    maxLength={1000}
                                    placeholder={t('Anything the professional should know — parking, pets, entry instructions...')}
                                />
                                <InputError message={errors.notes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="photos" className="flex items-center gap-1">
                                    <ImagePlus className="h-4 w-4" />
                                    {t('Problem photos (optional, up to 4)')}
                                </Label>
                                <input
                                    id="photos"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    multiple
                                    className="text-sm"
                                    onChange={(e) => setData('photos', Array.from(e.target.files ?? []).slice(0, 4))}
                                />
                                {data.photos.length > 0 && (
                                    <p className="text-muted-foreground text-xs">{t(':count photo(s) attached.', { count: data.photos.length })}</p>
                                )}
                                <InputError message={errors.photos} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Payment')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {paymentMethods.length === 0 && (
                                <p className="text-muted-foreground text-sm">{t('No payment method is available right now.')}</p>
                            )}
                            {paymentMethods.map((method) => {
                                const meta = methodMeta[method];

                                if (meta === undefined) {
                                    return null;
                                }

                                const Icon = meta.icon;
                                const disabled = !selectable(method);
                                const selected = data.payment_method === method;

                                return (
                                    <button
                                        key={method}
                                        type="button"
                                        disabled={disabled}
                                        onClick={() => setData('payment_method', method)}
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                                            selected && 'border-primary ring-primary ring-1',
                                            disabled && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <Icon className="h-5 w-5 shrink-0" />
                                        <div className="text-sm">
                                            <p className="font-medium">{meta.title}</p>
                                            <p className="text-muted-foreground">{meta.hint}</p>
                                        </div>
                                    </button>
                                );
                            })}
                            <InputError message={errors.payment_method} />
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Order summary')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {lines.map((line) => (
                                <div key={line.key} className="flex justify-between gap-2">
                                    <span className="text-muted-foreground min-w-0">
                                        {line.name} × {line.qty}
                                        {line.addon_names.length > 0 && <span className="block text-xs">+ {line.addon_names.join(', ')}</span>}
                                    </span>
                                    <span className="shrink-0">{money(line.line_total)}</span>
                                </div>
                            ))}
                            <Separator />

                            {coupon === null ? (
                                <div className="space-y-1 py-1">
                                    <div className="flex gap-2">
                                        <Input
                                            value={couponForm.data.coupon}
                                            onChange={(e) => couponForm.setData('coupon', e.target.value.toUpperCase())}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    applyCoupon();
                                                }
                                            }}
                                            placeholder={t('Coupon code')}
                                            className="h-9 font-mono uppercase"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-9"
                                            disabled={couponForm.processing || couponForm.data.coupon.trim() === ''}
                                            onClick={applyCoupon}
                                        >
                                            {couponForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                            {t('Apply')}
                                        </Button>
                                    </div>
                                    {/* Placement can also reject the coupon (raced cap) — that error
                                        lands on the main form's bag, not the apply form's. */}
                                    <InputError message={couponForm.errors.coupon ?? (errors as Record<string, string | undefined>).coupon} />
                                    {couponError !== null && <p className="text-xs text-amber-700 dark:text-amber-400">{couponError}</p>}
                                </div>
                            ) : (
                                <div className="flex items-center justify-between rounded-lg border border-dashed border-emerald-400 bg-emerald-50 px-3 py-2 dark:border-emerald-700 dark:bg-emerald-950/40">
                                    <span className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                        <TicketPercent className="h-4 w-4" />
                                        {coupon.code}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={removeCoupon}
                                        aria-label={t('Remove coupon')}
                                        className="text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            )}

                            <Separator />
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Items')}</span>
                                <span>{money(summary.subtotal)}</span>
                            </div>
                            {summary.discount !== undefined && Number(summary.discount) > 0 && (
                                <div className="flex justify-between text-emerald-700 dark:text-emerald-400">
                                    <span>{t('Coupon discount')}</span>
                                    <span>− {money(summary.discount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {summary.tax_label} ({summary.tax_percent}%)
                                </span>
                                <span>{money(summary.tax)}</span>
                            </div>
                            <Separator />
                            <div className="flex justify-between text-base font-semibold">
                                <span>{t('Total')}</span>
                                <span>{money(summary.total)}</span>
                            </div>
                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                size="lg"
                                disabled={processing || data.address_id === null || data.scheduled_at === null || paymentMethods.length === 0}
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {submitLabel}
                            </Button>
                            <p className="text-muted-foreground text-center text-xs">{submitHint}</p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </PublicLayout>
    );
}
