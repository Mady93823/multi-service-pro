import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

interface DetectOptions {
    /** Auto attempts stay silent on failure — a background prompt should not toast. */
    silent?: boolean;
    onUnsupported?: () => void;
    onError?: () => void;
}

/**
 * "Use my location": ask the browser for a GPS fix and post it to the server,
 * which resolves it to a service area (or the nearest one) and stores it in the
 * session. The server owns the geography — the browser only reports coordinates.
 */
export function useLocationDetect() {
    const [busy, setBusy] = useState(false);

    const detect = useCallback((options: DetectOptions = {}) => {
        if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
            options.onUnsupported?.();
            return;
        }

        setBusy(true);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                router.post(
                    route('location.detect'),
                    { lat: position.coords.latitude, lng: position.coords.longitude },
                    {
                        preserveScroll: true,
                        onFinish: () => setBusy(false),
                    },
                );
            },
            () => {
                setBusy(false);
                if (!options.silent) {
                    options.onError?.();
                }
            },
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 10 * 60 * 1000 },
        );
    }, []);

    return { detect, busy };
}
