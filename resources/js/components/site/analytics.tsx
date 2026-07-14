import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const CONSENT_KEY = 'cookie-consent';

/**
 * Analytics tags (M24).
 *
 * Consent first: when the cookie banner is switched on, nothing loads until the
 * visitor has accepted. The choice lives in localStorage and nowhere else (M19)
 * — the server must not learn who declined, because recording that would *be*
 * the tracking they refused. With the banner off, the operator has decided
 * their jurisdiction does not require it and the tags load normally.
 *
 * The IDs are validated to their known shapes on the server (`G-…`, `GTM-…`,
 * digits), so what goes into these snippets is an id, never an admin's script.
 */
export function Analytics() {
    const { site } = usePage<SharedData>().props;
    const analytics = site.analytics;
    const needsConsent = site.cookie !== null;

    const [allowed, setAllowed] = useState(false);

    useEffect(() => {
        if (!needsConsent) {
            setAllowed(true);

            return;
        }

        const decide = () => setAllowed(window.localStorage.getItem(CONSENT_KEY) === 'accepted');

        decide();

        // The banner writes the key on the same page — poll the storage event and
        // a short interval so accepting takes effect without a reload.
        window.addEventListener('storage', decide);
        const timer = window.setInterval(decide, 1000);

        return () => {
            window.removeEventListener('storage', decide);
            window.clearInterval(timer);
        };
    }, [needsConsent]);

    useEffect(() => {
        if (analytics === null || !allowed) {
            return;
        }

        const nodes: HTMLElement[] = [];

        const inject = (content: string, src?: string) => {
            const script = document.createElement('script');
            script.dataset.analytics = 'true';
            script.async = true;

            if (src !== undefined) {
                script.src = src;
            } else {
                script.textContent = content;
            }

            document.head.appendChild(script);
            nodes.push(script);
        };

        if (analytics.ga4_id !== null) {
            inject('', `https://www.googletagmanager.com/gtag/js?id=${analytics.ga4_id}`);
            inject(
                `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${analytics.ga4_id}');`,
            );
        }

        if (analytics.gtm_id !== null) {
            inject(
                `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${analytics.gtm_id}');`,
            );
        }

        if (analytics.meta_pixel_id !== null) {
            inject(
                `!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','${analytics.meta_pixel_id}');fbq('track','PageView');`,
            );
        }

        return () => nodes.forEach((node) => node.remove());
    }, [analytics, allowed]);

    return null;
}
