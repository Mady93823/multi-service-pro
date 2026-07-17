import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocationDetect } from '@/hooks/use-location-detect';
import { useTrans } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, LocateFixed, MapPin } from 'lucide-react';
import { toast } from 'sonner';

/**
 * The storefront city switcher (M25).
 *
 * "Use my location" resolves a GPS fix to the nearest service area server-side
 * (M03); the city list lets a visitor override it — they may be booking for
 * someone in another town. A single-city install still gets the location action
 * (it narrows the zone), just no city list to choose from.
 */
export default function CitySwitcher() {
    const { site } = usePage<SharedData>().props;
    const t = useTrans();
    const { detect, busy } = useLocationDetect();

    const { cities, active_city: active } = site;

    if (cities.length === 0 || active === null) {
        return null;
    }

    const requestLocation = () =>
        detect({
            onUnsupported: () => toast.error(t('Your browser cannot share your location.')),
            onError: () => toast.error(t('We could not read your location. Check your browser permissions.')),
        });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1">
                    <MapPin className="h-4 w-4" />
                    <span className="max-w-28 truncate">{active.name}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuItem
                    onSelect={(e) => {
                        // Keep the menu logic out of the geolocation callback's way.
                        e.preventDefault();
                        requestLocation();
                    }}
                    disabled={busy}
                    className="gap-2 font-medium"
                >
                    {busy ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <LocateFixed className="text-primary h-4 w-4" />}
                    {t('Use my location')}
                </DropdownMenuItem>

                {cities.length > 1 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel>{t('Choose your city')}</DropdownMenuLabel>
                        {cities.map((city) => (
                            <DropdownMenuItem
                                key={city.id}
                                onSelect={() => {
                                    if (city.id !== active.id) {
                                        // A POST: the choice changes what the storefront
                                        // offers, and it belongs in the session.
                                        router.post(route('city.switch', city.id), {}, { preserveScroll: true });
                                    }
                                }}
                            >
                                <span className="flex-1">{city.name}</span>
                                {city.id === active.id && <Check className="h-4 w-4" />}
                            </DropdownMenuItem>
                        ))}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
