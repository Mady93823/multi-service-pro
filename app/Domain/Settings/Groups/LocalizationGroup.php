<?php

namespace App\Domain\Settings\Groups;

class LocalizationGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'localization';
    }

    public function label(): string
    {
        return __('Localization');
    }

    public function description(): string
    {
        return __('The timezone every date is shown in, and the language the site starts in.');
    }

    /**
     * `localization.currency` is not here: the Currency screen owns it (M24), so
     * the code and the way it is printed are edited in one place. A screen group
     * is not a storage group (D24).
     */
    public function keys(): array
    {
        return ['localization.timezone', 'localization.locale'];
    }

    public function rules(array $input): array
    {
        return [
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[a-z]{2}([_-][A-Za-z]{2,4})?$/'],
        ];
    }

    public function values(): array
    {
        return [
            'timezone' => $this->settings->string('localization.timezone', 'Asia/Kolkata'),
            'locale' => $this->settings->string('localization.locale', 'en'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('localization.timezone', $data['timezone']);
        $this->settings->set('localization.locale', $data['locale']);
    }
}
