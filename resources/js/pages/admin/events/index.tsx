import { BookingStatusBadge } from '@/components/booking/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { useMoney } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type BookingStatus, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarHeart, ExternalLink, PartyPopper, Pencil, Plus, Sparkles, Star } from 'lucide-react';

interface EventService {
    id: number;
    name: string;
    price: string;
    is_active: boolean;
    is_featured: boolean;
}

interface EventChild {
    id: number;
    name: string;
    is_active: boolean;
    image_url: string | null;
    services: EventService[];
}

interface EventRoot {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    image_url: string | null;
    services_count: number;
    services: EventService[];
    children: EventChild[];
}

interface RecentEventBooking {
    id: number;
    customer: string;
    status: BookingStatus;
    scheduled_label: string;
    total: string;
    items_count: number;
}

interface EventHubProps {
    stats: { categories: number; services: number; bookings_30: number; revenue_30: string | number };
    roots: EventRoot[];
    recent: RecentEventBooking[];
}

/**
 * The Event Management hub — deliberately image-forward where the rest of the
 * admin is tables: events are sold on looks, and this screen shows the admin
 * exactly what the storefront's /events page is selling. All edits link into
 * the ordinary catalog CRUD (D42).
 */
export default function AdminEventsIndex({ stats, roots, recent }: EventHubProps) {
    const t = useTrans();
    const money = useMoney();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Dashboard'), href: '/admin/dashboard' },
        { title: t('Events'), href: '/admin/events' },
    ];

    const tiles = [
        { label: t('Event categories'), value: String(stats.categories) },
        { label: t('Event services'), value: String(stats.services) },
        { label: t('Bookings (30 days)'), value: String(stats.bookings_30) },
        { label: t('Revenue (30 days)'), value: money(stats.revenue_30) },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Events')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Hero — theme gradient with drawn-on decoration; no other admin screen has one. */}
                <section className="from-primary to-primary/70 text-primary-foreground relative overflow-hidden rounded-2xl bg-gradient-to-br p-6 sm:p-8">
                    <div className="pointer-events-none absolute -top-10 -right-10 h-48 w-48 rounded-full bg-white/10" aria-hidden />
                    <div className="pointer-events-none absolute right-24 -bottom-16 h-40 w-40 rounded-full bg-white/10" aria-hidden />
                    <PartyPopper className="pointer-events-none absolute right-8 bottom-6 h-24 w-24 opacity-15" aria-hidden />

                    <div className="relative flex flex-wrap items-start justify-between gap-4">
                        <div className="max-w-xl">
                            <p className="flex items-center gap-2 text-sm font-medium tracking-wide uppercase opacity-80">
                                <Sparkles className="h-4 w-4" /> {t('Event Management')}
                            </p>
                            <h1 className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                                {t('Weddings, birthdays and parties — your event business at a glance')}
                            </h1>
                            <p className="mt-2 text-sm opacity-80">
                                {t('Covers, packages and bookings for everything sold on the /events page. Edits open the ordinary catalog forms.')}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button asChild variant="secondary">
                                <a href={route('events.index')} target="_blank" rel="noopener noreferrer">
                                    <ExternalLink className="mr-1 h-4 w-4" /> {t('View public page')}
                                </a>
                            </Button>
                            <Button asChild variant="secondary">
                                <Link href={route('admin.categories.create')}>
                                    <Plus className="mr-1 h-4 w-4" /> {t('Add event category')}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div className="relative mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {tiles.map((tile) => (
                            <div key={tile.label} className="rounded-xl bg-white/10 p-4 backdrop-blur-sm">
                                <p className="text-xs tracking-wide uppercase opacity-75">{tile.label}</p>
                                <p className="mt-1 text-2xl font-bold">{tile.value}</p>
                            </div>
                        ))}
                    </div>
                </section>

                {roots.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <span className="bg-primary/10 text-primary flex h-12 w-12 items-center justify-center rounded-2xl">
                                <CalendarHeart className="h-6 w-6" />
                            </span>
                            <p className="font-medium">{t('No event categories yet')}</p>
                            <p className="text-muted-foreground max-w-md text-sm">
                                {t('Create a category and set "Shown on" to Events — it appears here and on the public /events page.')}
                            </p>
                            <Button asChild>
                                <Link href={route('admin.categories.create')}>{t('Add event category')}</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    roots.map((root) => (
                        <section key={root.id} className="overflow-hidden rounded-2xl border">
                            {/* Cover banner — the seeded artwork, or a quiet gradient until one is set. */}
                            <div className="relative h-36 sm:h-44">
                                {root.image_url !== null ? (
                                    <img src={root.image_url} alt={root.name} className="absolute inset-0 h-full w-full object-cover" />
                                ) : (
                                    <div className="from-primary/80 to-primary/40 absolute inset-0 bg-gradient-to-r" />
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                                <div className="absolute right-4 bottom-3 left-4 flex flex-wrap items-end justify-between gap-2">
                                    <div>
                                        <h2 className="text-xl font-bold text-white drop-shadow">{root.name}</h2>
                                        <p className="text-sm text-white/80">{t(':count services', { count: String(root.services_count) })}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {!root.is_active && <Badge variant="secondary">{t('Inactive')}</Badge>}
                                        <Button asChild size="sm" variant="secondary">
                                            <Link href={route('admin.categories.edit', root.id)}>
                                                <Pencil className="mr-1 h-3.5 w-3.5" /> {t('Edit')}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                {root.children.map((child) => (
                                    <div key={child.id} className="bg-card overflow-hidden rounded-xl border">
                                        <div className="relative h-24">
                                            {child.image_url !== null ? (
                                                <img src={child.image_url} alt={child.name} className="absolute inset-0 h-full w-full object-cover" />
                                            ) : (
                                                <div className="bg-muted absolute inset-0" />
                                            )}
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                                            <p className="absolute bottom-2 left-3 font-semibold text-white drop-shadow">{child.name}</p>
                                            {!child.is_active && (
                                                <Badge variant="secondary" className="absolute top-2 right-2">
                                                    {t('Inactive')}
                                                </Badge>
                                            )}
                                        </div>
                                        <ul className="divide-y text-sm">
                                            {child.services.map((service) => (
                                                <li key={service.id} className="flex items-center justify-between gap-2 px-3 py-2">
                                                    <span className="flex min-w-0 items-center gap-1.5">
                                                        {service.is_featured && (
                                                            <Star className="h-3.5 w-3.5 shrink-0 fill-amber-400 text-amber-400" />
                                                        )}
                                                        <Link href={route('admin.services.edit', service.id)} className="hover:text-primary truncate">
                                                            {service.name}
                                                        </Link>
                                                        {!service.is_active && (
                                                            <Badge variant="outline" className="shrink-0">
                                                                {t('Off')}
                                                            </Badge>
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground shrink-0 tabular-nums">{money(service.price)}</span>
                                                </li>
                                            ))}
                                            {child.services.length === 0 && (
                                                <li className="text-muted-foreground px-3 py-2">{t('No services yet')}</li>
                                            )}
                                        </ul>
                                    </div>
                                ))}

                                {root.services.length > 0 && (
                                    <div className="bg-card overflow-hidden rounded-xl border">
                                        <p className="text-muted-foreground px-3 pt-3 text-xs font-medium tracking-wide uppercase">
                                            {t('Directly under :name', { name: root.name })}
                                        </p>
                                        <ul className="divide-y text-sm">
                                            {root.services.map((service) => (
                                                <li key={service.id} className="flex items-center justify-between gap-2 px-3 py-2">
                                                    <Link href={route('admin.services.edit', service.id)} className="hover:text-primary truncate">
                                                        {service.name}
                                                    </Link>
                                                    <span className="text-muted-foreground shrink-0 tabular-nums">{money(service.price)}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        </section>
                    ))
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CalendarHeart className="text-primary h-4 w-4" /> {t('Recent event bookings')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('No event bookings yet.')}</p>
                        ) : (
                            <ul className="divide-y">
                                {recent.map((booking) => (
                                    <li key={booking.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                        <span className="flex min-w-0 items-center gap-3">
                                            <Link href={route('admin.bookings.show', booking.id)} className="hover:text-primary font-medium">
                                                #{booking.id}
                                            </Link>
                                            <span className="truncate">{booking.customer}</span>
                                            <BookingStatusBadge status={booking.status} />
                                        </span>
                                        <span className="flex items-center gap-4">
                                            <span className="text-muted-foreground">{booking.scheduled_label}</span>
                                            <span className="font-medium tabular-nums">{money(booking.total)}</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
