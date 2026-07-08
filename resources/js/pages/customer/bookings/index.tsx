import { BookingStatusBadge } from '@/components/booking/status-badge';
import { Pagination } from '@/components/catalog/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customer-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Booking, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ChevronRight } from 'lucide-react';

interface BookingsIndexProps {
    bookings: Paginated<Booking>;
}

export default function BookingsIndex({ bookings }: BookingsIndexProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/dashboard' },
        { title: t('My bookings'), href: '/bookings' },
    ];

    return (
        <CustomerLayout breadcrumbs={breadcrumbs}>
            <Head title={t('My bookings')} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('My bookings')}</h1>

                {bookings.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 py-20 text-center">
                        <CalendarClock className="text-muted-foreground/40 h-12 w-12" />
                        <p className="text-muted-foreground">{t('No bookings yet.')}</p>
                        <Button asChild>
                            <Link href={route('catalog.index')}>{t('Browse services')}</Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {bookings.data.map((booking) => (
                            <Link key={booking.id} href={route('bookings.show', booking.id)} className="block">
                                <Card className="hover:border-primary/50 transition-colors">
                                    <CardContent className="flex items-center gap-4 p-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{booking.items?.map((item) => item.name).join(', ')}</span>
                                                <BookingStatusBadge status={booking.status} />
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {booking.scheduled_label} · {booking.slot_label}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{booking.code}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold">{money(booking.total)}</span>
                                            <ChevronRight className="text-muted-foreground h-4 w-4" />
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                <Pagination meta={bookings.meta} links={bookings.links} />
            </div>
        </CustomerLayout>
    );
}
