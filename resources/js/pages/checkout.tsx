import { SlotPicker } from '@/components/booking/slot-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { Banknote, Check, CreditCard, ImagePlus, Landmark, LoaderCircle, MapPin, TicketPercent, Wallet, X } from 'lucide-react';
import { FormEventHandler, type ComponentType, type ReactNode } from 'react';

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
    /** The city the address's zone belongs to (M25) — the slot grid follows it. */
    city_id: number | null;
}

interface CheckoutPageProps {
    lines: CheckoutLine[];
    addresses: CheckoutAddress[];
    slot_days: SlotDay[];
    /** The city the offered slots were drawn for; null when the address is outside every zone. */
    slot_city: { id: number; name: string; timezone: string } | null;
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

/** A numbered step, so the page reads as a sequence rather than as four unrelated cards. */
function Step({ number, title, hint, children }: { number: number; title: string; hint?: ReactNode; children: ReactNode }) {
    return (
        <Card className="gap-0 p-6">
            <div className="mb-5 flex items-start gap-3">
                <span className="bg-primary/10 text-primary flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold">
                    {number}
                </span>
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    {hint !== undefined && <p className="text-muted-foreground mt-0.5 text-sm">{hint}</p>}
                </div>
            </div>
            {children}
        </Card>
    );
}

export default function CheckoutPage({
    lines,
    addresses,
    slot_days: slotDays,
    slot_city: slotCity,
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

    /**
     * Slots are drawn in the city's own clock (M25), so picking an address in
     * another city means asking the server for that city's grid — and dropping
     * the chosen slot, which was a different wall-clock hour.
     */
    const chooseAddress = (addressId: number, cityId: number | null) => {
        setData('address_id', addressId);

        if (cityId === (slotCity?.id ?? null)) {
            return;
        }

        setData('scheduled_at', null);

        // A partial reload keeps the half-filled form; only the grid is refetched.
        router.reload({
            only: ['slot_days', 'slot_city'],
            data: { address: addressId },
        });
    };

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
        offline: {
            icon: Landmark,
            title: t('Bank transfer'),
            hint: t('Transfer from your bank or UPI app; we confirm the booking once it lands.'),
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
        offline: t('Continue to bank details'),
    };
    const hints: Record<string, string> = {
        cash: t('No charge until the service is completed.'),
        wallet: t('Paid instantly from your wallet balance.'),
        offline: t('You will see where to transfer the money on the next screen.'),
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

            <h1 className="text-3xl font-bold tracking-tight">{t('Checkout')}</h1>

            <form onSubmit={submit} className="grid gap-8 py-8 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <Step number={1} title={t('Service address')}>
                        <div className="space-y-3">
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

                            {addresses.map(({ address, blocked_services: blocked, city_id: cityId }) => {
                                const isBlocked = blocked.length > 0;
                                const selected = data.address_id === address.id;

                                return (
                                    <button
                                        key={address.id}
                                        type="button"
                                        disabled={isBlocked}
                                        onClick={() => chooseAddress(address.id, cityId)}
                                        className={cn(
                                            'relative w-full rounded-xl border p-4 text-left text-sm transition-all',
                                            selected ? 'border-primary bg-primary/5 ring-primary/30 ring-2' : 'hover:border-primary/40',
                                            isBlocked && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold">{addressLabels[address.label]}</span>
                                            {address.is_default && (
                                                <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[11px] font-medium">
                                                    {t('Default')}
                                                </span>
                                            )}
                                            {selected && (
                                                <span className="bg-primary text-primary-foreground ml-auto flex h-5 w-5 items-center justify-center rounded-full">
                                                    <Check className="h-3 w-3" />
                                                </span>
                                            )}
                                        </div>

                                        <p className="text-muted-foreground mt-1">
                                            {address.line1}
                                            {address.line2 ? `, ${address.line2}` : ''} · {address.city} {address.postal_code}
                                        </p>

                                        {isBlocked && (
                                            <p className="text-highlight-foreground bg-highlight/15 dark:text-highlight mt-2 rounded-lg px-2 py-1 text-xs">
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
                        </div>
                    </Step>

                    <Step
                        number={2}
                        title={t('Pick a time')}
                        hint={slotCity ? t('Times are shown in :city local time.', { city: slotCity.name }) : undefined}
                    >
                        <SlotPicker days={slotDays} value={data.scheduled_at} onChange={(value) => setData('scheduled_at', value)} />
                        <InputError message={errors.scheduled_at} className="mt-2" />
                    </Step>

                    <Step number={3} title={t('Details for the professional')}>
                        <div className="space-y-5">
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
                                <Label htmlFor="photos" className="flex items-center gap-1.5">
                                    <ImagePlus className="h-4 w-4" />
                                    {t('Problem photos (optional, up to 4)')}
                                </Label>
                                <input
                                    id="photos"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    multiple
                                    className="file:bg-muted hover:file:bg-accent w-full cursor-pointer rounded-xl border p-2 text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                    onChange={(e) => setData('photos', Array.from(e.target.files ?? []).slice(0, 4))}
                                />
                                {data.photos.length > 0 && (
                                    <p className="text-muted-foreground text-xs">{t(':count photo(s) attached.', { count: data.photos.length })}</p>
                                )}
                                <InputError message={errors.photos} />
                            </div>
                        </div>
                    </Step>

                    <Step number={4} title={t('Payment')}>
                        <div className="space-y-3">
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
                                            'flex w-full items-center gap-3.5 rounded-xl border p-4 text-left transition-all',
                                            selected ? 'border-primary bg-primary/5 ring-primary/30 ring-2' : 'hover:border-primary/40',
                                            disabled && 'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                                selected ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            <Icon className="h-5 w-5" />
                                        </span>

                                        <div className="min-w-0 text-sm">
                                            <p className="font-semibold">{meta.title}</p>
                                            <p className="text-muted-foreground">{meta.hint}</p>
                                        </div>

                                        {selected && (
                                            <span className="bg-primary text-primary-foreground ml-auto flex h-5 w-5 shrink-0 items-center justify-center rounded-full">
                                                <Check className="h-3 w-3" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}

                            <InputError message={errors.payment_method} />
                        </div>
                    </Step>
                </div>

                <div>
                    <Card className="gap-0 p-6 shadow-lg lg:sticky lg:top-24">
                        <h2 className="text-lg font-semibold">{t('Order summary')}</h2>

                        <div className="mt-5 space-y-2.5 text-sm">
                            {lines.map((line) => (
                                <div key={line.key} className="flex justify-between gap-3">
                                    <span className="text-muted-foreground min-w-0">
                                        {line.name} × {line.qty}
                                        {line.addon_names.length > 0 && <span className="block text-xs">+ {line.addon_names.join(', ')}</span>}
                                    </span>
                                    <span className="shrink-0 font-medium">{money(line.line_total)}</span>
                                </div>
                            ))}
                        </div>

                        <Separator className="my-4" />

                        {coupon === null ? (
                            <div className="space-y-2">
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
                                        className="h-10 font-mono uppercase"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10"
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
                                {couponError !== null && <p className="text-highlight text-xs">{couponError}</p>}
                            </div>
                        ) : (
                            <div className="border-success/40 bg-success/10 flex items-center justify-between rounded-xl border border-dashed px-3 py-2.5">
                                <span className="text-success flex items-center gap-2 text-sm font-semibold">
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

                        <Separator className="my-4" />

                        <div className="space-y-2.5 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Items')}</span>
                                <span className="font-medium">{money(summary.subtotal)}</span>
                            </div>

                            {summary.discount !== undefined && Number(summary.discount) > 0 && (
                                <div className="text-success flex justify-between font-medium">
                                    <span>{t('Coupon discount')}</span>
                                    <span>− {money(summary.discount)}</span>
                                </div>
                            )}

                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {summary.tax_label} ({summary.tax_percent}%)
                                </span>
                                <span className="font-medium">{money(summary.tax)}</span>
                            </div>

                            <Separator />

                            <div className="flex items-center justify-between">
                                <span className="font-semibold">{t('Total')}</span>
                                <span className="text-2xl font-bold">{money(summary.total)}</span>
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="mt-6 h-12 w-full rounded-xl text-base"
                            size="lg"
                            disabled={processing || data.address_id === null || data.scheduled_at === null || paymentMethods.length === 0}
                        >
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {submitLabel}
                        </Button>

                        <p className="text-muted-foreground mt-3 text-center text-xs">{submitHint}</p>
                    </Card>
                </div>
            </form>
        </PublicLayout>
    );
}
