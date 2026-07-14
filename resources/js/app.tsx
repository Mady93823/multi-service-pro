import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { Toaster } from './components/ui/sonner';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type ReverbConfig = { key: string; host: string; port: number; scheme: string };

/**
 * Echo is configured from the SERVER's answer, not from `import.meta.env`.
 *
 * `public/build` ships prebuilt and the installer mints a fresh REVERB_APP_KEY
 * for every install, so a `VITE_REVERB_APP_KEY` baked in at compile time would
 * hand every buyer *our* key: Reverb would reject every connection, and live
 * tracking and the notification bell would fail silently on a site that looked
 * perfectly healthy (P7.3). A build-time constant cannot describe a runtime
 * install.
 */
const configureEchoFrom = (reverb: ReverbConfig | undefined) => {
    const secure = reverb?.scheme === 'https';

    configureEcho({
        broadcaster: 'reverb',
        key: reverb?.key ?? '',
        wsHost: reverb?.host ?? window.location.hostname,
        wsPort: reverb?.port ?? 8080,
        wssPort: reverb?.port ?? 443,
        forceTLS: secure,
        enabledTransports: secure ? ['wss'] : ['ws', 'wss'],
    });
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        configureEchoFrom((props.initialPage.props as { reverb?: ReverbConfig }).reverb);

        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster position="top-right" richColors />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
