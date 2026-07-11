<?php

namespace App\Domain\Localization\Actions;

use App\Domain\Settings\SettingsRegistry;
use App\Models\Language;
use Illuminate\Validation\ValidationException;

class DeleteLanguage
{
    public function __construct(private SettingsRegistry $settings) {}

    /**
     * Remove a language row and its lang/{code}.json file. Refused for the
     * default (`en`) and for whatever locale the site currently runs on —
     * deleting the active catalog would silently un-translate the whole app.
     */
    public function handle(Language $language): void
    {
        SaveTranslations::assertManagedCode($language);

        if ($language->code === $this->settings->string('localization.locale', 'en')) {
            throw ValidationException::withMessages([
                'language' => __('This language is the site locale — switch the site locale in Settings first.'),
            ]);
        }

        $path = lang_path($language->code.'.json');

        if (is_file($path)) {
            unlink($path);
        }

        $language->delete();
    }
}
