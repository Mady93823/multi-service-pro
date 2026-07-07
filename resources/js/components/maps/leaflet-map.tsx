import { DEFAULT_CENTER, DEFAULT_ZOOM, TILE_ATTRIBUTION, TILE_URL } from '@/lib/leaflet';
import { type ReactNode } from 'react';
import { MapContainer, TileLayer } from 'react-leaflet';

interface LeafletMapProps {
    center?: [number, number];
    zoom?: number;
    className?: string;
    children?: ReactNode;
}

/**
 * Base OpenStreetMap map. `z-0` keeps Leaflet's panes stacked below the
 * app's dialogs and dropdowns.
 */
export function LeafletMap({ center = DEFAULT_CENTER, zoom = DEFAULT_ZOOM, className, children }: LeafletMapProps) {
    return (
        <MapContainer center={center} zoom={zoom} scrollWheelZoom className={`relative z-0 h-96 w-full rounded-xl border ${className ?? ''}`}>
            <TileLayer url={TILE_URL} attribution={TILE_ATTRIBUTION} />
            {children}
        </MapContainer>
    );
}
