import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

type Replacements = Record<string, string | number>;

export type Translator = (key: string, replacements?: Replacements) => string;

/**
 * Resolve a translation key against a catalog, falling back to the key
 * itself (English source string) and substituting :placeholder tokens —
 * mirrors Laravel's JSON translation behavior.
 */
export function translate(translations: Record<string, string>, key: string, replacements?: Replacements): string {
    let value = translations[key] ?? key;

    if (replacements) {
        for (const [token, replacement] of Object.entries(replacements)) {
            value = value.replaceAll(`:${token}`, String(replacement));
        }
    }

    return value;
}

/**
 * Translation hook bound to the shared catalog for the active locale.
 * Keys must be literal strings (the catalog guard test scans for them).
 */
export function useTrans(): Translator {
    const { translations } = usePage<SharedData>().props;

    return useCallback((key, replacements) => translate(translations, key, replacements), [translations]);
}
