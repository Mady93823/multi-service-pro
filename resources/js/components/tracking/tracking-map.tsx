import { etaMinutes, haversineMeters, lerp, type LatLng } from '@/lib/geo';
import { getJson } from '@/lib/http';
import { useTrans } from '@/lib/i18n';
import { TILE_ATTRIBUTION, TILE_URL } from '@/lib/leaflet';
import { type Booking, type BookingStatus, type TrackingLast, type TrackingPayload } from '@/types';
import { router } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import L from 'leaflet';
import { Loader2, MapPin, Navigation, Wifi, WifiOff } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MapContainer, Marker, Polyline, TileLayer, useMap } from 'react-leaflet';

const STALE_AFTER_MS = 30_000;
const POLL_INTERVAL_MS = 10_000;
const ANIMATE_MS = 1_000;
const MAX_TRAIL = 40;

// Statuses where a moving map makes sense; before/after we show a calm state.
const LIVE_STATUSES: BookingStatus[] = ['en_route'];

function providerIcon(name: string): L.DivIcon {
    const initial = name.trim().charAt(0).toUpperCase() || '?';

    return L.divIcon({
        className: '',
        html: `<div style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9999px;background:#4f46e5;color:#fff;font-weight:600;font-size:14px;box-shadow:0 0 0 3px rgba(79,70,229,.35);">${initial}</div>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
    });
}

/**
 * Imperatively fits the map to provider + destination, and exposes a recenter
 * trigger. Auto-fit switches off once the user pans, so the map does not fight
 * the person reading it (05-Live-Tracking).
 */
function MapController({ points, recenterKey }: { points: LatLng[]; recenterKey: number }) {
    const map = useMap();
    const autoFit = useRef(true);

    useEffect(() => {
        const disable = () => {
            autoFit.current = false;
        };
        map.on('dragstart', disable);

        return () => {
            map.off('dragstart', disable);
        };
    }, [map]);

    useEffect(() => {
        if (!autoFit.current || points.length === 0) {
            return;
        }
        if (points.length === 1) {
            map.setView([points[0].lat, points[0].lng], Math.max(map.getZoom(), 14));

            return;
        }
        map.fitBounds(
            points.map((p) => [p.lat, p.lng]),
            { padding: [48, 48], maxZoom: 16 },
        );
    }, [map, points]);

    // Recenter button bumps recenterKey → re-enable auto-fit and refit.
    useEffect(() => {
        if (recenterKey === 0) {
            return;
        }
        autoFit.current = true;
        if (points.length > 0) {
            map.fitBounds(
                points.map((p) => [p.lat, p.lng]),
                { padding: [48, 48], maxZoom: 16 },
            );
        }
    }, [map, points, recenterKey]);

    return null;
}

export function TrackingMap({ booking }: { booking: Booking }) {
    const t = useTrans();
    const destination = useMemo<LatLng>(() => ({ lat: booking.address.lat, lng: booking.address.lng }), [booking.address.lat, booking.address.lng]);

    const [position, setPosition] = useState<LatLng | null>(null);
    const [trail, setTrail] = useState<LatLng[]>([]);
    const [speed, setSpeed] = useState<number | null>(null);
    const [lastAt, setLastAt] = useState<number | null>(null);
    const [status, setStatus] = useState<BookingStatus>(booking.status);
    const [recenterKey, setRecenterKey] = useState(0);

    const connection = useConnectionStatus();
    const animation = useRef<number | null>(null);

    // Smoothly slide the marker from where it is to the new fix over ~1s.
    const moveTo = useCallback((next: LatLng) => {
        setPosition((current) => {
            if (current === null) {
                return next;
            }
            const from = current;
            const start = performance.now();

            if (animation.current !== null) {
                cancelAnimationFrame(animation.current);
            }
            const step = (now: number) => {
                const progress = Math.min(1, (now - start) / ANIMATE_MS);
                setPosition(lerp(from, next, progress));
                if (progress < 1) {
                    animation.current = requestAnimationFrame(step);
                }
            };
            animation.current = requestAnimationFrame(step);

            return current;
        });
    }, []);

    const applyFix = useCallback(
        (lat: number, lng: number, spd: number | null, ts: string | null) => {
            const next = { lat, lng };
            moveTo(next);
            setSpeed(spd);
            setLastAt(ts ? Date.parse(ts) : Date.now());
            setTrail((prev) => {
                const last = prev[prev.length - 1];
                if (last && haversineMeters(last, next) < 2) {
                    return prev;
                }

                return [...prev, next].slice(-MAX_TRAIL);
            });
        },
        [moveTo],
    );

    // Live channel — LocationUpdated pings.
    useEcho<TrackingPayload>(
        `tracking.booking.${booking.id}`,
        '.LocationUpdated',
        (payload) => applyFix(payload.lat, payload.lng, payload.speed, payload.ts),
        [booking.id],
        'private',
    );

    // Live channel — status pushes (arrived → freeze, completed → summary).
    useEcho<{ status: BookingStatus }>(
        `tracking.booking.${booking.id}`,
        '.BookingStatusChanged',
        (payload) => {
            setStatus(payload.status);
            router.reload({ only: ['booking'] });
        },
        [booking.id],
        'private',
    );

    useEffect(() => {
        return () => {
            if (animation.current !== null) {
                cancelAnimationFrame(animation.current);
            }
        };
    }, []);

    // Polling fallback — kicks in when Echo is not connected or pings go stale.
    useEffect(() => {
        if (!LIVE_STATUSES.includes(status)) {
            return;
        }
        const poll = async () => {
            const fresh = lastAt !== null && Date.now() - lastAt < STALE_AFTER_MS;
            if (connection === 'connected' && fresh) {
                return;
            }
            try {
                const data = await getJson<TrackingLast>(route('tracking.last', booking.id));
                if (data.booking_status !== status) {
                    setStatus(data.booking_status);
                    router.reload({ only: ['booking'] });
                }
                if (data.lat !== null && data.lng !== null) {
                    applyFix(data.lat, data.lng, data.speed, data.ts);
                }
            } catch {
                // Offline / server hiccup — keep the last-known marker in place.
            }
        };
        const id = window.setInterval(poll, POLL_INTERVAL_MS);

        return () => window.clearInterval(id);
    }, [booking.id, status, connection, lastAt, applyFix]);

    const isLive = LIVE_STATUSES.includes(status);
    const stale = lastAt !== null && Date.now() - lastAt > STALE_AFTER_MS;
    const eta = position !== null ? etaMinutes(position, destination, speed) : null;

    const fitPoints = useMemo(() => (position !== null ? [position, destination] : [destination]), [position, destination]);

    if (status === 'arrived') {
        return (
            <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                <MapPin className="h-5 w-5 shrink-0" />
                <p>{t('Your professional has arrived.')}</p>
            </div>
        );
    }

    if (!isLive && status !== 'accepted') {
        return null;
    }

    return (
        <div className="overflow-hidden rounded-xl border">
            <div className="bg-muted/40 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-2 text-sm">
                <div className="flex items-center gap-2 font-medium">
                    <Navigation className="h-4 w-4" />
                    {status === 'accepted'
                        ? t('Your professional will set off soon.')
                        : eta !== null
                          ? t('Arriving in about :minutes min', { minutes: String(eta) })
                          : t('Locating your professional…')}
                </div>
                <div className="flex items-center gap-3">
                    {status === 'en_route' &&
                        (connection === 'connected' && !stale ? (
                            <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                <Wifi className="h-3.5 w-3.5" /> {t('Live')}
                            </span>
                        ) : (
                            <span className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                <WifiOff className="h-3.5 w-3.5" /> {t('Reconnecting…')}
                            </span>
                        ))}
                    <button type="button" onClick={() => setRecenterKey((k) => k + 1)} className="text-primary text-xs font-medium hover:underline">
                        {t('Recenter')}
                    </button>
                </div>
            </div>

            {position === null && status === 'accepted' ? (
                <div className="text-muted-foreground flex h-64 items-center justify-center gap-2 text-sm">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    {t('Waiting for the journey to start…')}
                </div>
            ) : (
                <MapContainer center={[destination.lat, destination.lng]} zoom={14} className="h-72 w-full sm:h-96" scrollWheelZoom>
                    <TileLayer url={TILE_URL} attribution={TILE_ATTRIBUTION} />
                    <Marker position={[destination.lat, destination.lng]} />
                    {position !== null && <Marker position={[position.lat, position.lng]} icon={providerIcon(booking.provider?.name ?? '?')} />}
                    {trail.length > 1 && (
                        <Polyline positions={trail.map((p) => [p.lat, p.lng])} pathOptions={{ color: '#4f46e5', weight: 4, opacity: 0.7 }} />
                    )}
                    <MapController points={fitPoints} recenterKey={recenterKey} />
                </MapContainer>
            )}
        </div>
    );
}
