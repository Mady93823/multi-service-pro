import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, MapPin } from 'lucide-react';

/**
 * The storefront city switcher (M25).
 *
 * The server detected the city from the customer's default address pin; this
 * only lets them say otherwise. A single-city install gets a plain label — a
 * dropdown with one choice is a control that does nothing.
 */
export default function CitySwitcher() {
    const { site } = usePage<SharedData>().props;
    const t = useTrans();

    const { cities, active_city: active } = site;

    if (cities.length === 0 || active === null) {
        return null;
    }

    if (cities.length === 1) {
        return (
            <span className="text-muted-foreground hidden items-center gap-1 text-sm sm:flex">
                <MapPin className="h-4 w-4" />
                {active.name}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1">
                    <MapPin className="h-4 w-4" />
                    <span className="max-w-28 truncate">{active.name}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-52">
                <DropdownMenuLabel>{t('Choose your city')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
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
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
