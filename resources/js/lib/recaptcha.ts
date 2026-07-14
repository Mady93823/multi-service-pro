/**
 * reCaptcha v3 in the browser (M24).
 *
 * Everything here is a no-op unless the server sent a site key AND ticked the
 * form — a fresh install loads no Google script, and every form submits exactly
 * as it did before. A failure to reach Google resolves to an empty token rather
 * than blocking the submit; the server fails open too.
 */
declare global {
    interface Window {
        grecaptcha?: {
            ready: (callback: () => void) => void;
            execute: (siteKey: string, options: { action: string }) => Promise<string>;
        };
    }
}

let loading: Promise<void> | null = null;

function load(siteKey: string): Promise<void> {
    if (window.grecaptcha !== undefined) {
        return Promise.resolve();
    }

    loading ??= new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('reCaptcha failed to load'));
        document.head.appendChild(script);
    });

    return loading;
}

/** A token for this action, or '' when reCaptcha is not in play (or is unreachable). */
export async function recaptchaToken(siteKey: string | null, action: string): Promise<string> {
    if (siteKey === null || siteKey === '') {
        return '';
    }

    try {
        await load(siteKey);

        return await new Promise<string>((resolve) => {
            window.grecaptcha?.ready(() => {
                window.grecaptcha
                    ?.execute(siteKey, { action })
                    .then(resolve)
                    .catch(() => resolve(''));
            });
        });
    } catch {
        return '';
    }
}
