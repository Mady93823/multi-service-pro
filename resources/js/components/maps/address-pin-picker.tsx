import { LeafletMap } from '@/components/maps/leaflet-map';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/lib/i18n';
import { PIN_ZOOM } from '@/lib/leaflet';
import type L from 'leaflet';
import { LocateFixed, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Marker, useMap, useMapEvents } from 'react-leaflet';

export interface ReverseGeocodeResult {
    lat: number;
    lng: number;
    line1: string;
    line2: string | null;
    city: string;
    postal_code: string;
    display_name: string;
}

interface SearchGeocodeResult {
    lat: number;
    lng: number;
    display_name: string;
}

export interface PinPoint {
    lat: number;
    lng: number;
}

interface AddressPinPickerProps {
    value: PinPoint | null;
    /** Fired when the pin moves; `address` is null when reverse geocoding was unavailable. */
    onChange: (point: PinPoint, address: ReverseGeocodeResult | null) => void;
}

async function reverseGeocode(point: PinPoint): Promise<ReverseGeocodeResult | null> {
    try {
        const response = await fetch(route('geocode.reverse', { lat: point.lat, lng: point.lng }), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return null;
        }

        const json = (await response.json()) as { result: ReverseGeocodeResult | null };

        return json.result;
    } catch {
        return null;
    }
}

function ClickToPin({ onPin }: { onPin: (point: PinPoint) => void }) {
    useMapEvents({
        click(event) {
            onPin({ lat: event.latlng.lat, lng: event.latlng.lng });
        },
    });

    return null;
}

/** Recenters the map when the pin is moved from outside the map (search, geolocate). */
function FlyToPin({ value }: { value: PinPoint | null }) {
    const map = useMap();
    const lat = value?.lat;
    const lng = value?.lng;

    useEffect(() => {
        if (lat !== undefined && lng !== undefined) {
            map.flyTo([lat, lng], Math.max(map.getZoom(), PIN_ZOOM), { duration: 0.6 });
        }
    }, [map, lat, lng]);

    return null;
}

export function AddressPinPicker({ value, onChange }: AddressPinPickerProps) {
    const t = useTrans();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchGeocodeResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [geoError, setGeoError] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const pin = async (point: PinPoint) => {
        onChangeRef.current(point, await reverseGeocode(point));
    };

    useEffect(() => {
        if (debounceRef.current !== null) {
            clearTimeout(debounceRef.current);
        }

        const term = query.trim();

        if (term.length < 3) {
            setResults([]);
            setSearching(false);

            return;
        }

        setSearching(true);

        debounceRef.current = setTimeout(async () => {
            try {
                const response = await fetch(route('geocode.search', { q: term }), {
                    headers: { Accept: 'application/json' },
                });
                const json = response.ok ? ((await response.json()) as { results: SearchGeocodeResult[] }) : { results: [] };
                setResults(json.results);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 450);

        return () => {
            if (debounceRef.current !== null) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [query]);

    const locateMe = () => {
        setGeoError(false);

        navigator.geolocation.getCurrentPosition(
            (position) => void pin({ lat: position.coords.latitude, lng: position.coords.longitude }),
            () => setGeoError(true),
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <div className="relative flex-1">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('Search for an area or landmark…')}
                        className="pl-9"
                        aria-label={t('Search for an area or landmark…')}
                    />
                    {(results.length > 0 || searching) && (
                        <div className="bg-popover text-popover-foreground absolute z-10 mt-1 w-full rounded-md border shadow-md">
                            {searching && <p className="text-muted-foreground px-3 py-2 text-sm">{t('Searching…')}</p>}
                            {!searching &&
                                results.map((result) => (
                                    <button
                                        key={`${result.lat},${result.lng}`}
                                        type="button"
                                        className="hover:bg-accent block w-full px-3 py-2 text-left text-sm"
                                        onClick={() => {
                                            setQuery('');
                                            setResults([]);
                                            void pin({ lat: result.lat, lng: result.lng });
                                        }}
                                    >
                                        {result.display_name}
                                    </button>
                                ))}
                        </div>
                    )}
                </div>
                <Button type="button" variant="outline" onClick={locateMe}>
                    <LocateFixed className="h-4 w-4" />
                    {t('Use my location')}
                </Button>
            </div>

            {geoError && <p className="text-destructive text-sm">{t('Could not read your location. Move the pin manually instead.')}</p>}

            <LeafletMap>
                <ClickToPin onPin={(point) => void pin(point)} />
                <FlyToPin value={value} />
                {value !== null && (
                    <Marker
                        position={[value.lat, value.lng]}
                        draggable
                        eventHandlers={{
                            dragend(event) {
                                const latlng = (event.target as L.Marker).getLatLng();
                                void pin({ lat: latlng.lat, lng: latlng.lng });
                            },
                        }}
                    />
                )}
            </LeafletMap>

            <p className="text-muted-foreground text-sm">{t('Tap the map or drag the pin to your exact entrance.')}</p>
        </div>
    );
}
