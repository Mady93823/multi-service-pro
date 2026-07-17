import { BookingTimeline } from '@/components/booking/booking-timeline';
import { BookingStatusBadge, useBookingStatusLabels } from '@/components/booking/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BookingStatus, type BreadcrumbItem, type DispatchOffer, type Payment } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight, Ban, FileText, Radar, Undo2, UserRound } from 'lucide-react';
import { useState } from 'react';

interface AdminBookingShowProps {
    booking: Booking;
    offers: DispatchOffer[];
    can_dispatch: boolean;
    payments: Payment[];
    can_refund: boolean;
    can_download_invoice: boolean;
    /** Snapshotted at completion (M09); null until the job is done. */
    earning: { commission_rate: string; commission_amount: string; provider_earning: string } | null;
    allowed_transitions: BookingStatus[];
    providers: { id: number; name: string }[];
}

const offerStatusStyles: Record<string, string> = {
    offered: 'text-amber-700 dark:text-amber-300',
    accepted: 'text-emerald-700 dark:text-emerald-400',
    declined: 'text-red-700 dark:text-red-400',
    expired: 'text-muted-foreground',
};

function useOfferStatusLabels(): Record<string, string> {
    const t = useTrans();

    return {
        offered: t('Offered'),
        accepted: t('Accepted'),
        declined: t('Declined'),
        expired: t('Expired'),
    };
}

interface TransitionErrors {
    errors: Record<string, string>;
    [key: string]: unknown;
}

const paymentStatusStyles: Record<string, string> = {
    initiated: 'text-amber-700 dark:text-amber-300',
    captured: 'text-emerald-700 dark:text-emerald-400',
    failed: 'text-red-700 dark:text-red-400',
    refunded: 'text-muted-foreground',
};

function usePaymentStatusLabels(): Record<string, string> {
    const t = useTrans();

    return {
        initiated: t('Initiated'),
        captured: t('Captured'),
        failed: t('Failed'),
        refunded: t('Refunded'),
    };
}

export default function AdminBookingShow({
    booking,
    offers,
    can_dispatch: canDispatch,
    payments,
    can_refund: canRefund,
    can_download_invoice: canDownloadInvoice,
    earning,
    allowed_transitions: allowed,
    providers,
}: AdminBookingShowProps) {
    const t = useTrans();
    const money = useMoney();
    const statusLabels = useBookingStatusLabels();
    const offerStatusLabels = useOfferStatusLabels();
    const paymentStatusLabels = usePaymentStatusLabels();
    const { errors } = usePage<TransitionErrors>().props;

    const [providerId, setProviderId] = useState<string>(booking.provider?.id.toString() ?? '');
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelNote, setCancelNote] = useState('');
    const [refundOpen, setRefundOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const refund = () => {
        router.post(
            route('admin.bookings.refund', booking.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setRefundOpen(false);
                },
            },
        );
    };

    const capturedTotal = payments.filter((payment) => payment.status === 'captured').reduce((sum, payment) => sum + Number(payment.amount), 0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Bookings'), href: '/admin/bookings' },
        { title: booking.code, href: `/admin/bookings/${booking.id}` },
    ];

    const transition = (to: BookingStatus, extra: Record<string, unknown> = {}) => {
        router.post(
            route('admin.bookings.transition', booking.id),
            { to, ...extra },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setCancelOpen(false);
                },
            },
        );
    };

    const advanceTargets = allowed.filter((to) => to !== 'assigned' && to !== 'cancelled_admin');

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={booking.code} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">{booking.code}</h1>
                        <p className="text-muted-foreground text-sm">
                            {booking.scheduled_label} · {booking.slot_label}
                        </p>
                    </div>
                    <BookingStatusBadge status={booking.status} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('Manage status')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {allowed.length === 0 && <p className="text-muted-foreground text-sm">{t('This booking is in a final state.')}</p>}

                                {allowed.includes('assigned') && (
                                    <div className="flex flex-wrap items-end gap-2">
                                        <div className="grid gap-2">
                                            <Label>{t('Assign professional')}</Label>
                                            <Select value={providerId} onValueChange={setProviderId}>
                                                <SelectTrigger className="w-64">
                                                    <SelectValue placeholder={t('Pick a professional')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {providers.map((provider) => (
                                                        <SelectItem key={provider.id} value={provider.id.toString()}>
                                                            {provider.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            disabled={providerId === '' || processing}
                                            onClick={() => transition('assigned', { provider_id: Number(providerId) })}
                                        >
                                            <UserRound className="h-4 w-4" />
                                            {t('Assign')}
                                        </Button>
                                    </div>
                                )}
                                <InputError message={errors.provider_id} />

                                {advanceTargets.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {advanceTargets.map((to) => (
                                            <Button key={to} variant="outline" disabled={processing} onClick={() => transition(to)}>
                                                <ArrowRight className="h-4 w-4" />
                                                {statusLabels[to]}
                                            </Button>
                                        ))}
                                    </div>
                                )}

                                {allowed.includes('cancelled_admin') && (
                                    <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                                        <DialogTrigger asChild>
                                            <Button variant="outline" className="text-destructive">
                                                <Ban className="h-4 w-4" />
                                                {t('Cancel booking')}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>{t('Cancel this booking?')}</DialogTitle>
                                                <DialogDescription>{t('The customer will see the reason you enter.')}</DialogDescription>
                                            </DialogHeader>
                                            <div className="grid gap-2">
                                                <Label htmlFor="cancel-note">{t('Reason')}</Label>
                                                <Textarea
                                                    id="cancel-note"
                                                    value={cancelNote}
                                                    onChange={(e) => setCancelNote(e.target.value)}
                                                    rows={3}
                                                    maxLength={500}
                                                />
                                                <InputError message={errors.note} />
                                            </div>
                                            <DialogFooter>
                                                <Button variant="outline" onClick={() => setCancelOpen(false)}>
                                                    {t('Keep booking')}
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    disabled={cancelNote.trim() === '' || processing}
                                                    onClick={() => transition('cancelled_admin', { note: cancelNote })}
                                                >
                                                    {t('Cancel booking')}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                )}
                                <InputError message={errors.to} />

                                {booking.status === 'arrived' && (
                                    <p className="text-muted-foreground text-xs">
                                        {t('Starting the job as admin skips the customer OTP; the audit trail records you as the actor.')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {(canDispatch || offers.length > 0) && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base">{t('Dispatch')}</CardTitle>
                                    {canDispatch && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => router.post(route('admin.bookings.dispatch', booking.id), {}, { preserveScroll: true })}
                                        >
                                            <Radar className="h-4 w-4" />
                                            {t('Run dispatch')}
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <InputError message={errors.dispatch} />
                                    {offers.length === 0 && <p className="text-muted-foreground">{t('No offers sent yet.')}</p>}
                                    {offers.map((offer) => (
                                        <div key={offer.id} className="flex items-center justify-between gap-2">
                                            <span className="min-w-0 truncate">
                                                {offer.provider?.name ?? '—'}
                                                {offer.distance_km !== null && (
                                                    <span className="text-muted-foreground"> · {Number(offer.distance_km).toFixed(1)} km</span>
                                                )}
                                            </span>
                                            <span className={offerStatusStyles[offer.status]}>{offerStatusLabels[offer.status]}</span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {(payments.length > 0 || canRefund) && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-base">{t('Payments')}</CardTitle>
                                    {canRefund && (
                                        <Dialog open={refundOpen} onOpenChange={setRefundOpen}>
                                            <DialogTrigger asChild>
                                                <Button size="sm" variant="outline" className="text-destructive">
                                                    <Undo2 className="h-4 w-4" />
                                                    {t('Refund')}
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>{t('Refund this booking?')}</DialogTitle>
                                                    <DialogDescription>
                                                        {t(
                                                            'This credits :amount to the customer wallet and marks every captured payment refunded. It cannot be undone.',
                                                            { amount: money(capturedTotal) },
                                                        )}
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <InputError message={errors.refund} />
                                                <DialogFooter>
                                                    <Button variant="outline" onClick={() => setRefundOpen(false)}>
                                                        {t('Keep payment')}
                                                    </Button>
                                                    <Button variant="destructive" disabled={processing} onClick={refund}>
                                                        {t('Refund to wallet')}
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {payments.length === 0 && <p className="text-muted-foreground">{t('No payment recorded yet.')}</p>}
                                    {payments.map((payment) => (
                                        <div key={payment.id} className="flex items-center justify-between gap-2">
                                            <span className="min-w-0">
                                                <span className="font-medium capitalize">{payment.gateway}</span>
                                                {payment.gateway_ref !== null && (
                                                    <span className="text-muted-foreground block truncate text-xs">{payment.gateway_ref}</span>
                                                )}
                                            </span>
                                            <span className="flex shrink-0 items-center gap-3">
                                                <span>{money(payment.amount)}</span>
                                                <span className={paymentStatusStyles[payment.status]}>{paymentStatusLabels[payment.status]}</span>
                                            </span>
                                        </div>
                                    ))}
                                    {canDownloadInvoice && (
                                        // A plain link, not an Inertia visit: the response is a PDF download.
                                        <Button asChild variant="outline" size="sm" className="mt-1 w-fit">
                                            <a href={route('bookings.invoice', booking.id)}>
                                                <FileText className="h-4 w-4" />
                                                {t('Download invoice')}
                                            </a>
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {earning !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('Commission')}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            {t('Platform commission')} · {earning.commission_rate}%
                                        </span>
                                        <span>{money(earning.commission_amount)}</span>
                                    </div>
                                    <div className="flex justify-between font-medium">
                                        <span>{t('Professional earns')}</span>
                                        <span className={Number(earning.provider_earning) < 0 ? 'text-red-700 dark:text-red-400' : ''}>
                                            {money(earning.provider_earning)}
                                        </span>
                                    </div>
                                    {Number(earning.provider_earning) < 0 && (
                                        // Cash job: the professional took the customer's money at the
                                        // door, so they owe the platform its commission and the tax.
                                        <p className="text-muted-foreground text-xs">
                                            {t('The professional collected cash, so this amount is deducted from their next payout.')}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('Booking details')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                {booking.items?.map((item) => (
                                    <div key={item.id} className="flex justify-between gap-2">
                                        <span className="min-w-0">
                                            {item.name} × {item.qty}
                                            {item.addons.length > 0 && (
                                                <span className="text-muted-foreground block text-xs">
                                                    + {item.addons.map((addon) => addon.name).join(', ')}
                                                </span>
                                            )}
                                        </span>
                                        <span className="shrink-0">{money(item.line_total)}</span>
                                    </div>
                                ))}
                                <Separator />
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('Items')}</span>
                                    <span>{money(Number(booking.subtotal) + Number(booking.addon_total))}</span>
                                </div>
                                {booking.tax_breakup !== null && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            {booking.tax_breakup.label} ({booking.tax_breakup.percent}%)
                                        </span>
                                        <span>{money(booking.tax)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-base font-semibold">
                                    <span>{t('Total')}</span>
                                    <span>{money(booking.total)}</span>
                                </div>
                                {booking.cancellation_fee !== null && Number(booking.cancellation_fee) > 0 && (
                                    <div className="flex justify-between text-red-700 dark:text-red-400">
                                        <span>{t('Cancellation fee')}</span>
                                        <span>{money(booking.cancellation_fee)}</span>
                                    </div>
                                )}
                                <Separator />
                                <p className="flex items-center gap-2">
                                    <span className="text-muted-foreground">{t('Customer')}:</span> {booking.customer?.name}
                                    {booking.customer != null && (
                                        <button
                                            type="button"
                                            onClick={() => router.post(route('admin.impersonate.store', booking.customer?.id))}
                                            className="text-primary text-xs underline-offset-2 hover:underline"
                                        >
                                            {t('Login as customer')}
                                        </button>
                                    )}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">{t('Professional')}:</span> {booking.provider?.name ?? '—'}
                                </p>
                                {booking.contact_phone !== null && (
                                    <p>
                                        <span className="text-muted-foreground">{t('Contact phone')}:</span> {booking.contact_phone}
                                        {booking.contact_phone_alt ? ` · ${booking.contact_phone_alt}` : ''}
                                    </p>
                                )}
                                <p>
                                    <span className="text-muted-foreground">{t('Address')}:</span> {booking.address.line1}
                                    {booking.address.line2 ? `, ${booking.address.line2}` : ''} · {booking.address.city} {booking.address.postal_code}
                                </p>
                                {booking.zone != null && (
                                    <p>
                                        <span className="text-muted-foreground">{t('Zone')}:</span> {booking.zone}
                                    </p>
                                )}
                                {booking.notes !== null && (
                                    <p>
                                        <span className="text-muted-foreground">{t('Notes')}:</span> {booking.notes}
                                    </p>
                                )}
                                {booking.cancel_reason !== null && (
                                    <p className="text-red-700 dark:text-red-400">
                                        {t('Cancellation reason: :reason', { reason: booking.cancel_reason })}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {booking.photo_urls !== undefined && booking.photo_urls.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">{t('Photos')}</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    {booking.photo_urls.map((url) => (
                                        <a key={url} href={url} target="_blank" rel="noreferrer">
                                            <img src={url} alt="" className="h-24 w-24 rounded-lg border object-cover" />
                                        </a>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">{t('Status updates')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <BookingTimeline history={booking.history ?? []} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
