import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ProviderLayout from '@/layouts/provider-layout';
import { haversineMeters, type LatLng } from '@/lib/geo';
import { postJson } from '@/lib/http';
import { useTrans } from '@/lib/i18n';
import { TILE_ATTRIBUTION, TILE_URL } from '@/lib/leaflet';
import { type Booking, type BreadcrumbItem, type TrackingConfig } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEchoPresence } from '@laravel/echo-react';
import { CheckCircle2, Eye, MapPin, Navigation, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet';

interface JourneyProps {
    booking: Booking;
    session: { status: string; last_lat: number | null; last_lng: number | null } | null;
    config: TrackingConfig;
}

interface PresenceMember {
    id: number;
    name: string;
    role: string;
}

interface PresenceChannelLike {
    here(cb: (members: PresenceMember[]) => void): unknown;
    joining(cb: (member: PresenceMember) => void): unknown;
    leaving(cb: (member: PresenceMember) => void): unknown;
}

interface WakeLockLike {
    release(): Promise<void>;
}

function RecenterOnMove({ point }: { point: LatLng | null }) {
    const map = useMap();
    useEffect(() => {
        if (point !== null) {
            map.setView([point.lat, point.lng], Math.max(map.getZoom(), 15));
        }
    }, [map, point]);

    return null;
}

export default function ProviderJourney({ booking, session, config }: JourneyProps) {
    const t = useTrans();

    const [status, setStatus] = useState(booking.status);
    const [position, setPosition] = useState<LatLng | null>(
        session?.last_lat != null && session.last_lng != null ? { lat: session.last_lat, lng: session.last_lng } : null,
    );
    const [permissionDenied, setPermissionDenied] = useState(false);
    const [busy, setBusy] = useState(false);
    const [watchers, setWatchers] = useState(0);

    const destination: LatLng = { lat: booking.address.lat, lng: booking.address.lng };
    const lastSent = useRef<{ point: LatLng; at: number } | null>(null);
    const wakeLock = useRef<WakeLockLike | null>(null);

    const presence = useEchoPresence(`tracking.booking.${booking.id}`);
    const presenceRef = useRef(presence);
    presenceRef.current = presence;

    // Presence: light up "customer is watching" from the shared channel roster.
    useEffect(() => {
        let cancelled = false;
        let attached = false;
        const isCustomer = (m: PresenceMember) => m.role === 'customer';
        const attach = () => {
            if (cancelled || attached) {
                return;
            }
            const channel = presenceRef.current.channel() as PresenceChannelLike | null;
            if (channel === null) {
                window.setTimeout(attach, 400);

                return;
            }
            attached = true;
            channel.here((members) => setWatchers(members.filter(isCustomer).length));
            channel.joining((member) => isCustomer(member) && setWatchers((w) => w + 1));
            channel.leaving((member) => isCustomer(member) && setWatchers((w) => Math.max(0, w - 1)));
        };
        attach();

        return () => {
            cancelled = true;
        };
    }, [booking.id]);

    const sendPing = useCallback(
        (coords: GeolocationCoordinates) => {
            const next: LatLng = { lat: coords.latitude, lng: coords.longitude };
            const now = Date.now();
            const previous = lastSent.current;

            // Client-side throttle: honour the interval and skip tiny hops to
            // save battery + bandwidth (05-Live-Tracking).
            if (previous !== null) {
                const elapsed = now - previous.at;
                if (elapsed < config.ping_interval_seconds * 1000 && haversineMeters(previous.point, next) < config.min_move_meters) {
                    return;
                }
            }
            lastSent.current = { point: next, at: now };
            setPosition(next);

            void postJson(route('provider.tracking.ping', booking.id), {
                lat: next.lat,
                lng: next.lng,
                accuracy: coords.accuracy,
                heading: Number.isNaN(coords.heading) ? null : coords.heading,
                speed: coords.speed === null ? null : coords.speed * 3.6,
                recorded_at: new Date(now).toISOString(),
            }).catch(() => {
                // A dropped ping is fine — the next fix will catch the map up.
            });
        },
        [booking.id, config.ping_interval_seconds, config.min_move_meters],
    );

    // GPS watch — only while actually en route.
    useEffect(() => {
        if (status !== 'en_route') {
            return;
        }
        if (!('geolocation' in navigator)) {
            setPermissionDenied(true);

            return;
        }
        const watchId = navigator.geolocation.watchPosition((pos) => sendPing(pos.coords), onGeoError, {
            enableHighAccuracy: true,
            maximumAge: 2000,
            timeout: 10000,
        });

        function onGeoError(error: GeolocationPositionError) {
            if (error.code === error.PERMISSION_DENIED) {
                setPermissionDenied(true);
            }
        }

        return () => navigator.geolocation.clearWatch(watchId);
    }, [status, sendPing]);

    // Keep the screen awake during the journey where the API exists.
    useEffect(() => {
        if (status !== 'en_route') {
            return;
        }
        const nav = navigator as Navigator & { wakeLock?: { request(type: 'screen'): Promise<WakeLockLike> } };
        let released = false;
        nav.wakeLock
            ?.request('screen')
            .then((lock) => {
                if (released) {
                    void lock.release();

                    return;
                }
                wakeLock.current = lock;
            })
            .catch(() => {
                // Wake lock is best-effort; ignore if the browser refuses.
            });

        return () => {
            released = true;
            void wakeLock.current?.release();
            wakeLock.current = null;
        };
    }, [status]);

    const startJourney = async () => {
        setBusy(true);
        try {
            await postJson(route('provider.tracking.start', booking.id));
            setStatus('en_route');
        } catch {
            setBusy(false);
        } finally {
            setBusy(false);
        }
    };

    const arrive = async () => {
        setBusy(true);
        try {
            await postJson(route('provider.tracking.stop', booking.id));
            router.visit(route('provider.jobs.index'));
        } catch {
            setBusy(false);
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Jobs'), href: '/provider/jobs' },
        { title: booking.code, href: `/provider/jobs/${booking.id}/journey` },
    ];

    return (
        <ProviderLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Live journey')} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">{t('Live journey')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {booking.code} · {booking.customer?.name}
                        </p>
                    </div>
                    {status === 'en_route' && (
                        <span className="flex items-center gap-1 text-sm text-emerald-600 dark:text-emerald-400">
                            <Eye className="h-4 w-4" />
                            {watchers > 0 ? t('Customer is watching') : t('Sharing your location')}
                        </span>
                    )}
                </div>

                <Card>
                    <CardContent className="flex items-start gap-3 p-4 text-sm">
                        <MapPin className="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-medium">{t('Destination')}</p>
                            <p className="text-muted-foreground">
                                {booking.address.line1}
                                {booking.address.line2 ? `, ${booking.address.line2}` : ''}, {booking.address.city}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {permissionDenied && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-medium">{t('Location access is off')}</p>
                            <p>
                                {t(
                                    'Turn on location for this site in your browser settings, then reload. The customer cannot see you move until location is on.',
                                )}
                            </p>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border">
                    <MapContainer center={[destination.lat, destination.lng]} zoom={14} className="h-72 w-full sm:h-96" scrollWheelZoom>
                        <TileLayer url={TILE_URL} attribution={TILE_ATTRIBUTION} />
                        <Marker position={[destination.lat, destination.lng]} />
                        {position !== null && <Marker position={[position.lat, position.lng]} />}
                        <RecenterOnMove point={position} />
                    </MapContainer>
                </div>

                {status === 'accepted' && (
                    <Button size="lg" className="h-14 text-base" onClick={startJourney} disabled={busy}>
                        <Navigation className="h-5 w-5" />
                        {t('Start journey')}
                    </Button>
                )}

                {status === 'en_route' && (
                    <Button size="lg" className="h-14 text-base" onClick={arrive} disabled={busy}>
                        <CheckCircle2 className="h-5 w-5" />
                        {t('I have arrived')}
                    </Button>
                )}
            </div>
        </ProviderLayout>
    );
}
