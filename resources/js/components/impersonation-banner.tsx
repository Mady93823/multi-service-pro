import { useTrans } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

/**
 * Full-width warning strip shown on every shell while an admin is browsing as
 * another user (M13). Deliberately loud — an admin must never forget whose
 * session they are inside. The leave control posts to the auth-only stop
 * route, which restores the admin session.
 */
export function ImpersonationBanner() {
    const { impersonation } = usePage<SharedData>().props;
    const t = useTrans();

    if (!impersonation) {
        return null;
    }

    return (
        <div
            role="alert"
            className="sticky top-0 z-50 flex items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
        >
            <ShieldAlert className="size-4 shrink-0" aria-hidden />
            <span>{t('Impersonating :name — actions here are real.', { name: impersonation.user_name })}</span>
            <button
                type="button"
                onClick={() => router.delete('/impersonate')}
                className="rounded-md bg-amber-950/90 px-3 py-1 text-xs font-semibold text-amber-50 hover:bg-amber-950"
            >
                {t('Leave')}
            </button>
        </div>
    );
}
