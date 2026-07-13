import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STORAGE_KEY = 'cookie-consent';

/**
 * Consent banner (M19). The choice is stored client-side only — the server
 * never learns who declined, which is the point: recording the decline would
 * itself be the tracking the visitor just refused. Analytics (M24) reads the
 * same key before it injects anything.
 */
export function CookieBanner() {
    const { site } = usePage<SharedData>().props;
    const t = useTrans();
    const [decided, setDecided] = useState(true);

    const cookie = site.cookie;

    // Read after mount: localStorage does not exist while Inertia is
    // server-rendering the first response.
    useEffect(() => {
        setDecided(window.localStorage.getItem(STORAGE_KEY) !== null);
    }, []);

    if (cookie === null || decided) {
        return null;
    }

    const decide = (choice: 'accepted' | 'declined') => {
        window.localStorage.setItem(STORAGE_KEY, choice);
        setDecided(true);
    };

    return (
        <div className="fixed inset-x-0 bottom-0 z-50 p-4">
            <div className="bg-background mx-auto flex w-full max-w-3xl flex-col gap-3 rounded-xl border p-4 shadow-lg sm:flex-row sm:items-center">
                <p className="text-muted-foreground flex-1 text-sm">
                    {cookie.message}{' '}
                    {cookie.policy_slug !== null && (
                        <Link href={`/p/${cookie.policy_slug}`} className="text-foreground underline underline-offset-2">
                            {t('Read more')}
                        </Link>
                    )}
                </p>
                <div className="flex items-center gap-2">
                    {cookie.decline_label !== null && (
                        <Button variant="outline" size="sm" onClick={() => decide('declined')}>
                            {cookie.decline_label}
                        </Button>
                    )}
                    <Button size="sm" onClick={() => decide('accepted')}>
                        {cookie.accept_label}
                    </Button>
                </div>
            </div>
        </div>
    );
}
