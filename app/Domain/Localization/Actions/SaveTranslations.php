<?php

namespace App\Domain\Localization\Actions;

use App\Domain\Localization\TranslationLoader;
use App\Models\Language;
use Illuminate\Validation\ValidationException;
use JsonException;

class SaveTranslations
{
    public function __construct(private TranslationLoader $loader) {}

    /**
     * Write lang/{code}.json for a managed language.
     *
     * Security posture (laravel-security, ADR D20): the locale code is
     * re-validated here against Language::CODE_PATTERN even though the row
     * came from the database — the code is the filename, and this action is
     * the only writer, so the guard lives with the write. `en` is refused:
     * that catalog belongs to the reconcile script / catalog guard test.
     *
     * Only keys present in the English catalog are kept (a stale or forged
     * payload cannot grow the file), and blank values are dropped so the
     * frontend falls back to the English source string.
     *
     * @param  array<string, mixed>  $translations
     * @return int number of translated strings written
     */
    public function handle(Language $language, array $translations): int
    {
        $this->guardWritablePath($language);

        $catalog = $this->loader->forLocale(Language::DEFAULT_CODE);

        $kept = [];
        foreach ($translations as $key => $value) {
            if (array_key_exists($key, $catalog) && is_string($value) && trim($value) !== '') {
                $kept[$key] = $value;
            }
        }

        ksort($kept, SORT_STRING);

        try {
            $json = json_encode(
                $kept === [] ? (object) [] : $kept,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            throw ValidationException::withMessages(['translations' => __('Translations could not be encoded.')]);
        }

        file_put_contents(lang_path($language->code.'.json'), $json.PHP_EOL, LOCK_EX);

        return count($kept);
    }

    /**
     * Shared with DeleteLanguage: a language file may only be touched when
     * the code passes the strict locale pattern and is not the source locale.
     */
    public static function assertManagedCode(Language $language): void
    {
        if ($language->isDefault()) {
            throw ValidationException::withMessages([
                'language' => __('The default language catalog is generated from source code and cannot be edited here.'),
            ]);
        }

        if (preg_match(Language::CODE_PATTERN, $language->code) !== 1) {
            throw ValidationException::withMessages([
                'language' => __('This language code is invalid.'),
            ]);
        }
    }

    private function guardWritablePath(Language $language): void
    {
        self::assertManagedCode($language);
    }
}
