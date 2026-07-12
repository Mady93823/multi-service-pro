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
        return __('Currency, timezone and language defaults.');
    }

    public function keys(): array
    {
        return ['localization.currency', 'localization.timezone', 'localization.locale'];
    }

    public function rules(array $input): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[a-z]{2}([_-][A-Za-z]{2,4})?$/'],
        ];
    }

    public function values(): array
    {
        return [
            'currency' => $this->settings->string('localization.currency', 'INR'),
            'timezone' => $this->settings->string('localization.timezone', 'Asia/Kolkata'),
            'locale' => $this->settings->string('localization.locale', 'en'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('localization.currency', $data['currency']);
        $this->settings->set('localization.timezone', $data['timezone']);
        $this->settings->set('localization.locale', $data['locale']);
    }
}
