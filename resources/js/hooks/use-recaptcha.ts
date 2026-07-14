import { recaptchaToken } from '@/lib/recaptcha';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Mints a reCaptcha token for one form (M24), or an empty string when the
 * install has no keys or this form is not protected. Call it inside the submit
 * handler and put the result in the payload — the server's rule ignores it in
 * exactly the cases this returns ''.
 */
export function useRecaptcha(form: string): () => Promise<string> {
    const { recaptcha } = usePage<SharedData>().props;

    const active = recaptcha !== null && recaptcha.forms[form] === true;

    return () => (active ? recaptchaToken(recaptcha.site_key, form) : Promise.resolve(''));
}
