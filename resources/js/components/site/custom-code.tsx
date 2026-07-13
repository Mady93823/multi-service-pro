import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Injects the admin's CSS/JS into the storefront (ADR D26).
 *
 * The nodes are built with `textContent`, never `dangerouslySetInnerHTML` — a
 * <script> tag React renders into the tree does not execute, and innerHTML for
 * a snippet that legitimately contains `</script>` would truncate it. The
 * effect removes what it added, so a client-side navigation into a page that
 * ships no snippet leaves nothing behind.
 *
 * The server only sends this prop on storefront routes, and only when the
 * feature is switched on — the admin panel never runs the snippet, so a broken
 * one cannot lock an admin out of the screen that removes it.
 */
export function CustomCode() {
    const { site } = usePage<SharedData>().props;
    const css = site.custom_code?.css ?? null;
    const js = site.custom_code?.js ?? null;

    useEffect(() => {
        if (css === null) {
            return;
        }

        const style = document.createElement('style');
        style.dataset.customCode = 'css';
        style.textContent = css;
        document.head.appendChild(style);

        return () => style.remove();
    }, [css]);

    useEffect(() => {
        if (js === null) {
            return;
        }

        const script = document.createElement('script');
        script.dataset.customCode = 'js';
        script.textContent = js;
        document.body.appendChild(script);

        return () => script.remove();
    }, [js]);

    return null;
}
