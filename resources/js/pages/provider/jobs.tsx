import { BookingStatusBadge } from '@/components/booking/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ProviderLayout from '@/layouts/provider-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BookingStatus, type BreadcrumbItem, type DispatchOffer } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock, MapPin, Navigation, Phone, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ProviderJobsProps {
    offers: DispatchOffer[];
    jobs: Booking[];
    recent: Booking[];
}

interface PageErrors {
    errors: Record<string, string>;
    [key: string]: unknown;
}

function serviceSummary(booking: Booking): string {
    return (booking.items ?? []).map((item) => `${item.name} × ${item.qty}`).join(', ');
}

function distanceLabel(km: string | null): string | null {
    if (km === null) {
        return null;
    }

    return `${Number(km).toFixed(1)} km`;
}

function OfferCard({ offer }: { offer: DispatchOffer }) {
    const t = useTrans();
    const money = useMoney();
    const [processing, setProcessing] = useState(false);
    const booking = offer.booking;

    if (booking === undefined) {
        return null;
    }

    const respond = (action: 'accept' | 'decline') => {
        router.post(
            route(action === 'accept' ? 'provider.offers.accept' : 'provider.offers.decline', offer.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const distance = distanceLabel(offer.distance_km);

    return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <p className="font-medium">{serviceSummary(booking)}</p>
                    <p className="text-muted-foreground text-sm">
                        {booking.scheduled_label} · {booking.slot_label}
                    </p>
                    <p className="text-muted-foreground flex items-center gap-1 text-sm">
                        <MapPin className="h-3.5 w-3.5" />
                        {booking.address.city}
                        {distance !== null && <span>· {distance}</span>}
                    </p>
                </div>
                <p className="text-right font-semibold">{money(booking.total)}</p>
            </div>
            <div className="mt-3 flex gap-2">
                <Button size="sm" disabled={processing} onClick={() => respond('accept')}>
                    <CheckCircle2 className="h-4 w-4" />
                    {t('Accept')}
                </Button>
                <Button size="sm" variant="outline" disabled={processing} onClick={() => respond('decline')}>
                    <XCircle className="h-4 w-4" />
                    {t('Decline')}
                </Button>
            </div>
        </div>
    );
}

function StartJobDialog({ bookingId }: { bookingId: number }) {
    const t = useTrans();
    const { errors } = usePage<PageErrors>().props;
    const [open, setOpen] = useState(false);
    const [otp, setOtp] = useState('');
    const [processing, setProcessing] = useState(false);

    const start = () => {
        router.post(
            route('provider.jobs.advance', bookingId),
            { to: 'in_progress', otp },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setOtp('');
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <ArrowRight className="h-4 w-4" />
                    {t('Start job')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Start job')}</DialogTitle>
                    <DialogDescription>{t('Ask the customer for their 4-digit start code.')}</DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="otp">{t('Start code')}</Label>
                    <Input
                        id="otp"
                        inputMode="numeric"
                        maxLength={4}
                        value={otp}
                        onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                        className="w-32 text-center text-lg tracking-widest"
                    />
                    <InputError message={errors.otp} />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        {t('Cancel')}
                    </Button>
                    <Button disabled={otp.length !== 4 || processing} onClick={start}>
                        {t('Start job')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeclineJobDialog({ bookingId }: { bookingId: number }) {
    const t = useTrans();
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const decline = () => {
        router.post(
            route('provider.jobs.advance', bookingId),
            { to: 'cancelled_provider', note },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setNote('');
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline" className="text-destructive">
                    {t("Can't take it")}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Give this job up?')}</DialogTitle>
                    <DialogDescription>{t('It goes back to dispatch for another professional.')}</DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="decline-note">{t('Reason (optional)')}</Label>
                    <Textarea id="decline-note" value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={500} />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        {t('Keep job')}
                    </Button>
                    <Button variant="destructive" disabled={processing} onClick={decline}>
                        {t("Can't take it")}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function JobCard({ booking }: { booking: Booking }) {
    const t = useTrans();
    const money = useMoney();
    const [processing, setProcessing] = useState(false);

    const advance = (to: BookingStatus) => {
        router.post(
            route('provider.jobs.advance', booking.id),
            { to },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const forwardButton = () => {
        switch (booking.status) {
            case 'assigned':
                return (
                    <Button size="sm" disabled={processing} onClick={() => advance('accepted')}>
                        <CheckCircle2 className="h-4 w-4" />
                        {t('Accept job')}
                    </Button>
                );
            case 'accepted':
                return (
                    <Button size="sm" onClick={() => router.visit(route('provider.jobs.journey', booking.id))}>
                        <Navigation className="h-4 w-4" />
                        {t('Start travelling')}
                    </Button>
                );
            case 'en_route':
                return (
                    <Button size="sm" onClick={() => router.visit(route('provider.jobs.journey', booking.id))}>
                        <Navigation className="h-4 w-4" />
                        {t('Live journey')}
                    </Button>
                );
            case 'arrived':
                return <StartJobDialog bookingId={booking.id} />;
            case 'in_progress':
                return (
                    <Button size="sm" disabled={processing} onClick={() => advance('completed')}>
                        <CheckCircle2 className="h-4 w-4" />
                        {t('Complete job')}
                    </Button>
                );
            default:
                return null;
        }
    };

    return (
        <div className="rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{booking.code}</span>
                        <BookingStatusBadge status={booking.status} />
                    </div>
                    <p className="text-sm">{serviceSummary(booking)}</p>
                    <p className="text-muted-foreground text-sm">
                        {booking.scheduled_label} · {booking.slot_label}
                    </p>
                    <p className="text-muted-foreground flex items-center gap-1 text-sm">
                        <MapPin className="h-3.5 w-3.5" />
                        {booking.address.line1}, {booking.address.city}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {t('Customer')}: {booking.customer?.name ?? '—'}
                    </p>
                    {booking.contact_phone && (
                        <p className="text-muted-foreground flex items-center gap-1 text-sm">
                            <Phone className="h-3.5 w-3.5" />
                            <a href={`tel:${booking.contact_phone}`} className="hover:text-foreground hover:underline">
                                {booking.contact_phone}
                            </a>
                            {booking.contact_phone_alt && (
                                <>
                                    <span>·</span>
                                    <a href={`tel:${booking.contact_phone_alt}`} className="hover:text-foreground hover:underline">
                                        {booking.contact_phone_alt}
                                    </a>
                                </>
                            )}
                        </p>
                    )}
                </div>
                <p className="text-right font-semibold">{money(booking.total)}</p>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
                {forwardButton()}
                {booking.status === 'assigned' && <DeclineJobDialog bookingId={booking.id} />}
            </div>
        </div>
    );
}

export default function ProviderJobs({ offers, jobs, recent }: ProviderJobsProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Jobs'), href: '/provider/jobs' }];

    return (
        <ProviderLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Jobs')} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Jobs')}</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('New offers')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {offers.length === 0 && <p className="text-muted-foreground text-sm">{t('No new offers right now.')}</p>}
                        {offers.map((offer) => (
                            <OfferCard key={offer.id} offer={offer} />
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Active jobs')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {jobs.length === 0 && <p className="text-muted-foreground text-sm">{t('No active jobs.')}</p>}
                        {jobs.map((booking) => (
                            <JobCard key={booking.id} booking={booking} />
                        ))}
                    </CardContent>
                </Card>

                {recent.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Recently completed')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {recent.map((booking) => (
                                <div key={booking.id} className="flex items-center justify-between gap-2">
                                    <span className="text-muted-foreground flex items-center gap-1">
                                        <Clock className="h-3.5 w-3.5" />
                                        {booking.code} · {booking.scheduled_label}
                                    </span>
                                    <span>{money(booking.total)}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </ProviderLayout>
    );
}
