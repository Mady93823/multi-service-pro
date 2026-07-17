import { useLocationDetect } from '@/hooks/use-location-detect';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const ATTEMPT_KEY = 'urbanserve:location-detect-attempted';

/**
 * Headless: on the first home visit, quietly ask a guest's browser for their
 * location and let the server pick their service area. Runs once per browser
 * (a localStorage flag), only for guests — a signed-in customer's saved address
 * is already the source of truth — and never re-prompts a browser that has
 * refused before. The manual "Use my location" button stays available to all.
 */
export default function LocationAutoDetect() {
    const { auth, site } = usePage<SharedData>().props;
    const { detect } = useLocationDetect();
    const ran = useRef(false);

    useEffect(() => {
        if (ran.current) {
            return;
        }
        ran.current = true;

        if (auth.user !== null || site.cities.length === 0 || typeof window === 'undefined') {
            return;
        }

        let attempted: string | null = null;
        try {
            attempted = window.localStorage.getItem(ATTEMPT_KEY);
        } catch {
            attempted = null;
        }
        if (attempted !== null) {
            return;
        }

        const run = () => {
            try {
                window.localStorage.setItem(ATTEMPT_KEY, '1');
            } catch {
                // Private mode — worst case we attempt again next visit.
            }
            detect({ silent: true });
        };

        // Never re-open the native prompt on a browser that already said no.
        if (navigator.permissions?.query !== undefined) {
            navigator.permissions
                .query({ name: 'geolocation' as PermissionName })
                .then((status) => {
                    if (status.state !== 'denied') {
                        run();
                    }
                })
                .catch(() => run());
        } else {
            run();
        }
    }, [auth.user, site.cities.length, detect]);

    return null;
}
