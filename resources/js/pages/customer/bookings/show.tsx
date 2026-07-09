import { BookingTimeline } from '@/components/booking/booking-timeline';
import { CancelBookingDialog } from '@/components/booking/cancel-booking-dialog';
import { RescheduleDialog } from '@/components/booking/reschedule-dialog';
import { BookingStatusBadge } from '@/components/booking/status-badge';
import { TrackingMap } from '@/components/tracking/tracking-map';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BreadcrumbItem, type SlotDay } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Heart, KeyRound, MapPin, RotateCcw, UserRound } from 'lucide-react';

interface BookingShowProps {
    booking: Booking;
    abilities: {
        can_cancel: boolean;
        can_reschedule: boolean;
        can_rebook: boolean;
        cancellation_fee_preview: string | null;
    };
    slot_days: SlotDay[];
    is_favorite_provider: boolean;
}

export default function BookingShow({ booking, abilities, slot_days: slotDays, is_favorite_provider: isFavorite }: BookingShowProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My bookings'), href: '/bookings' },
        { title: booking.code, href: `/bookings/${booking.id}` },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={booking.code} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">{booking.code}</h1>
                        <p className="text-muted-foreground text-sm">
                            {booking.scheduled_label} · {booking.slot_label}
                        </p>
                    </div>
                    <BookingStatusBadge status={booking.status} />
                </div>

                {booking.job_otp_code !== undefined && (
                    <Card className="border-primary/50">
                        <CardContent className="flex items-center gap-3 p-4">
                            <KeyRound className="text-primary h-5 w-5 shrink-0" />
                            <div className="text-sm">
                                <p className="font-medium">{t('Job start code: :code', { code: booking.job_otp_code })}</p>
                                <p className="text-muted-foreground">
                                    {t('Share this code with the professional when they arrive — the job starts only with it.')}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {booking.cancel_reason !== null && (
                    <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        {t('Cancellation reason: :reason', { reason: booking.cancel_reason })}
                    </p>
                )}

                <TrackingMap booking={booking} />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">{t('Services')}</CardTitle>
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
                                <p className="text-muted-foreground text-xs">
                                    {booking.payment_method === 'cash' ? t('Pay after service') : t('Paid online')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MapPin className="h-4 w-4" />
                                    {t('Service address')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                <p>
                                    {booking.address.line1}
                                    {booking.address.line2 ? `, ${booking.address.line2}` : ''}
                                </p>
                                <p className="text-muted-foreground">
                                    {booking.address.city} {booking.address.postal_code}
                                </p>
                                {booking.notes !== null && (
                                    <p className="text-muted-foreground mt-2 text-xs">{t('Notes: :notes', { notes: booking.notes })}</p>
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

                        {booking.provider && (
                            <Card>
                                <CardContent className="flex items-center gap-3 p-4">
                                    <UserRound className="text-muted-foreground h-5 w-5" />
                                    <span className="flex-1 text-sm font-medium">{booking.provider.name}</span>
                                    <Button
                                        variant={isFavorite ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => router.post(route('providers.favorite', booking.provider?.id), {}, { preserveScroll: true })}
                                    >
                                        <Heart className={isFavorite ? 'h-4 w-4 fill-current' : 'h-4 w-4'} />
                                        {isFavorite ? t('Favorited') : t('Favorite')}
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex flex-wrap gap-2">
                            {abilities.can_reschedule && <RescheduleDialog bookingId={booking.id} days={slotDays} />}
                            {abilities.can_cancel && <CancelBookingDialog bookingId={booking.id} feePreview={abilities.cancellation_fee_preview} />}
                            {abilities.can_rebook && (
                                <Button
                                    variant="outline"
                                    onClick={() => router.post(route('bookings.rebook', booking.id), {}, { preserveScroll: true })}
                                >
                                    <RotateCcw className="h-4 w-4" />
                                    {t('Book again')}
                                </Button>
                            )}
                        </div>
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
        </CustomerLayout>
    );
}
