import { SlotPicker } from '@/components/booking/slot-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/public-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type Address, type CartSummary, type SlotDay } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Banknote, ImagePlus, LoaderCircle, MapPin } from 'lucide-react';
import { FormEventHandler } from 'react';

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
}

type CheckoutForm = {
    address_id: number | null;
    scheduled_at: string | null;
    payment_method: string;
    notes: string;
    photos: File[];
};

export default function CheckoutPage({ lines, addresses, slot_days: slotDays, summary }: CheckoutPageProps) {
    const t = useTrans();
    const money = useMoney();

    const defaultAddress = addresses.find((entry) => entry.address.is_default && entry.blocked_services.length === 0);

    const { data, setData, post, processing, errors } = useForm<CheckoutForm>({
        address_id: defaultAddress?.address.id ?? null,
        scheduled_at: null,
        payment_method: 'cash',
        notes: '',
        photos: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('checkout.store'), { forceFormData: true, preserveScroll: true });
    };

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
                        <CardContent>
                            <div className="border-primary ring-primary flex items-center gap-3 rounded-lg border p-3 ring-1">
                                <Banknote className="h-5 w-5" />
                                <div className="text-sm">
                                    <p className="font-medium">{t('Pay after service')}</p>
                                    <p className="text-muted-foreground">{t('Pay in cash or UPI once the job is done.')}</p>
                                </div>
                            </div>
                            <p className="text-muted-foreground mt-2 text-xs">{t('Online payment is coming soon.')}</p>
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
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">{t('Items')}</span>
                                <span>{money(summary.subtotal)}</span>
                            </div>
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
                                disabled={processing || data.address_id === null || data.scheduled_at === null}
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {t('Place booking')}
                            </Button>
                            <p className="text-muted-foreground text-center text-xs">{t('No charge until the service is completed.')}</p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </PublicLayout>
    );
}
