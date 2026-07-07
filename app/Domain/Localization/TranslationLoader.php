<?php

namespace App\Domain\Localization;

use JsonException;

class TranslationLoader
{
    /**
     * Load the JSON translation catalog for a locale.
     *
     * Missing or malformed files resolve to an empty catalog — the
     * frontend helper falls back to the key itself, so the app keeps
     * working with untranslated (English) strings.
     *
     * @return array<string, string>
     */
    public function forLocale(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if (! is_file($path)) {
            return [];
        }

        try {
            $translations = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($translations)) {
            return [];
        }

        return array_filter($translations, fn ($value) => is_string($value));
    }
}
