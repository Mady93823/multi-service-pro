// Client-side geo maths for live tracking (05-Live-Tracking). Mirrors the
// PHP Haversine used server-side (ADR D12) — small enough to duplicate.

export interface LatLng {
    lat: number;
    lng: number;
}

const EARTH_RADIUS_M = 6_371_000;

function toRad(deg: number): number {
    return (deg * Math.PI) / 180;
}

/** Great-circle distance in metres between two points. */
export function haversineMeters(a: LatLng, b: LatLng): number {
    const dLat = toRad(b.lat - a.lat);
    const dLng = toRad(b.lng - a.lng);
    const lat1 = toRad(a.lat);
    const lat2 = toRad(b.lat);

    const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

    return 2 * EARTH_RADIUS_M * Math.asin(Math.min(1, Math.sqrt(h)));
}

/**
 * Rough ETA in minutes from provider → destination. Uses a rolling speed with
 * a floor so a stopped-but-moving marker never shows an infinite ETA
 * (05-Live-Tracking: min 15 km/h floor).
 */
export function etaMinutes(provider: LatLng, destination: LatLng, speedKmh: number | null): number {
    const meters = haversineMeters(provider, destination);
    const kmh = Math.max(speedKmh ?? 0, 15);

    return Math.max(1, Math.round((meters / 1000 / kmh) * 60));
}

/** Linear interpolation between two points for smooth marker animation. */
export function lerp(from: LatLng, to: LatLng, t: number): LatLng {
    return {
        lat: from.lat + (to.lat - from.lat) * t,
        lng: from.lng + (to.lng - from.lng) * t,
    };
}

/**
 * Google Maps directions deep link (D44). A plain URL — no API key, no SDK —
 * that opens the Maps app on a phone and a browser tab on desktop. The origin
 * is deliberately omitted: Maps then navigates from the device's own live
 * location, which is always fresher than our last tracking ping. Falls back
 * to a text destination when the snapshot carries no usable pin.
 */
export function googleMapsDirectionsUrl(destination: Partial<LatLng>, fallbackQuery?: string): string {
    const hasPin = typeof destination.lat === 'number' && typeof destination.lng === 'number' && !(destination.lat === 0 && destination.lng === 0);

    const target = hasPin ? `${destination.lat},${destination.lng}` : (fallbackQuery ?? '');

    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(target)}&travelmode=driving`;
}
