import { BookingStatusBadge } from '@/components/booking/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, ChevronRight, CreditCard, MapPin, Navigation, Search, Sparkles, Star, Wallet } from 'lucide-react';

interface DashboardStats {
    completed: number;
    upcoming: number;
    wallet_balance: string;
    addresses: number;
}

interface CustomerDashboardProps {
    /** The booking whose provider is moving right now, if any. */
    live: Booking | null;
    awaiting_payment: Booking[];
    upcoming: Booking[];
    to_review: Booking[];
    recent: Booking[];
    stats: DashboardStats;
}

/** One booking, as a tappable row. The shape every list on this page uses. */
function BookingRow({ booking }: { booking: Booking }) {
    const money = useMoney();
    const services = booking.items?.map((item) => item.name).join(', ');

    return (
        <Link href={route('bookings.show', booking.id)} className="block">
            <Card className="hover:border-primary/50 transition-colors">
                <CardContent className="flex items-center gap-4 p-4">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="truncate font-medium">{services || booking.code}</span>
                            <BookingStatusBadge status={booking.status} />
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {booking.scheduled_label} · {booking.slot_label}
                        </p>
                    </div>
                    <span className="shrink-0 font-semibold">{money(booking.total)}</span>
                    <ChevronRight className="text-muted-foreground h-4 w-4 shrink-0" aria-hidden />
                </CardContent>
            </Card>
        </Link>
    );
}

function SectionHeading({ title, action }: { title: string; action?: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
            {action}
        </div>
    );
}

export default function CustomerDashboard({
    live,
    awaiting_payment: awaitingPayment,
    upcoming,
    to_review: toReview,
    recent,
    stats,
}: CustomerDashboardProps) {
    const t = useTrans();
    const money = useMoney();
    const { auth } = usePage<SharedData>().props;

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('Dashboard'), href: '/dashboard' }];

    // A brand new customer has no bookings, no wallet and no history. Zeroes in
    // tiles would be an accurate way of saying nothing — they get the one thing
    // this page can usefully offer them instead.
    const isNewHere = stats.upcoming === 0 && stats.completed === 0 && recent.length === 0;

    const tiles = [
        { label: t('Upcoming'), value: String(stats.upcoming), icon: CalendarClock, href: route('bookings.index') },
        { label: t('Completed'), value: String(stats.completed), icon: Sparkles, href: route('bookings.index') },
        { label: t('Wallet'), value: money(stats.wallet_balance), icon: Wallet, href: route('wallet.show') },
        { label: t('Addresses'), value: String(stats.addresses), icon: MapPin, href: route('addresses.index') },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">{t('Hello, :name', { name: auth.user?.name ?? '' })}</h1>
                        <p className="text-muted-foreground text-sm">{t('Book a professional, track them to your door.')}</p>
                    </div>
                    <Button asChild className="rounded-xl">
                        <Link href={route('catalog.index')}>
                            <Search className="h-4 w-4" />
                            {t('Book a service')}
                        </Link>
                    </Button>
                </div>

                {/* Money first: an unpaid booking is never dispatched and expires
                    on its own, so it is the only thing here with a deadline. */}
                {awaitingPayment.map((booking) => (
                    <Card key={booking.id} className="border-highlight/40 bg-highlight/10">
                        <CardContent className="flex flex-wrap items-center gap-4 p-4">
                            <CreditCard className="text-highlight h-5 w-5 shrink-0" aria-hidden />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{t('Payment needed to confirm this booking')}</p>
                                <p className="text-muted-foreground mt-0.5 text-sm">
                                    {booking.code} · {booking.scheduled_label} · {money(booking.total)}
                                </p>
                            </div>
                            <Button asChild size="sm" className="rounded-lg">
                                <Link href={route('bookings.pay', booking.id)}>{t('Pay now')}</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}

                {/* The reason this screen exists on the day it matters. */}
                {live && (
                    <Card className="border-primary/40 bg-primary/5">
                        <CardContent className="flex flex-wrap items-center gap-4 p-4">
                            <span className="relative flex h-2.5 w-2.5 shrink-0" aria-hidden>
                                <span className="bg-primary absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" />
                                <span className="bg-primary relative inline-flex h-2.5 w-2.5 rounded-full" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">
                                        {live.provider ? t(':name is on the way', { name: live.provider.name }) : t('Your booking is under way')}
                                    </p>
                                    <BookingStatusBadge status={live.status} />
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-sm">{live.items?.map((item) => item.name).join(', ') || live.code}</p>
                            </div>
                            <Button asChild size="sm" className="rounded-lg">
                                <Link href={route('bookings.show', live.id)}>
                                    <Navigation className="h-4 w-4" />
                                    {t('Track')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {isNewHere ? (
                    <div className="flex flex-col items-center gap-4 py-16 text-center">
                        <span className="bg-muted text-muted-foreground flex h-16 w-16 items-center justify-center rounded-2xl">
                            <CalendarClock className="h-7 w-7" />
                        </span>
                        <div className="space-y-1">
                            <p className="font-semibold">{t('No bookings yet.')}</p>
                            <p className="text-muted-foreground text-sm">{t('Pick a service and we will find a professional near you.')}</p>
                        </div>
                        <Button asChild size="lg" className="rounded-xl">
                            <Link href={route('catalog.index')}>{t('Browse services')}</Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {tiles.map((tile) => (
                                <Link key={tile.label} href={tile.href}>
                                    <Card className="hover:border-primary/50 h-full transition-colors">
                                        <CardContent className="p-4">
                                            <tile.icon className="text-muted-foreground h-4 w-4" aria-hidden />
                                            <p className="mt-2 truncate text-lg font-bold">{tile.value}</p>
                                            <p className="text-muted-foreground truncate text-xs">{tile.label}</p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>

                        {toReview.length > 0 && (
                            <div className="space-y-3">
                                <SectionHeading title={t('How did it go?')} />
                                {toReview.map((booking) => (
                                    <Card key={booking.id}>
                                        <CardContent className="flex flex-wrap items-center gap-4 p-4">
                                            <Star className="text-muted-foreground h-5 w-5 shrink-0" aria-hidden />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">
                                                    {booking.items?.map((item) => item.name).join(', ') || booking.code}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 text-sm">
                                                    {booking.provider
                                                        ? t('with :name · :date', { name: booking.provider.name, date: booking.scheduled_label })
                                                        : booking.scheduled_label}
                                                </p>
                                            </div>
                                            <Button asChild size="sm" variant="outline" className="rounded-lg">
                                                <Link href={route('bookings.show', booking.id)}>{t('Rate')}</Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        {upcoming.length > 0 && (
                            <div className="space-y-3">
                                <SectionHeading
                                    title={t('Upcoming')}
                                    action={
                                        <Link href={route('bookings.index')} className="text-primary text-sm font-medium hover:underline">
                                            {t('See all')}
                                        </Link>
                                    }
                                />
                                {upcoming.map((booking) => (
                                    <BookingRow key={booking.id} booking={booking} />
                                ))}
                            </div>
                        )}

                        {recent.length > 0 && (
                            <div className="space-y-3">
                                <SectionHeading
                                    title={t('Recent')}
                                    action={
                                        <Link href={route('bookings.index')} className="text-primary text-sm font-medium hover:underline">
                                            {t('See all')}
                                        </Link>
                                    }
                                />
                                {recent.map((booking) => (
                                    <BookingRow key={booking.id} booking={booking} />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </CustomerLayout>
    );
}
