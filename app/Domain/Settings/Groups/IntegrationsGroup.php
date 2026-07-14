<?php

namespace App\Domain\Settings\Groups;

class IntegrationsGroup extends SettingsGroup
{
    /** Write-only, like every credential since M08: the screen gets `*_set`, never the value. */
    private const SECRETS = [
        'fcm_credentials' => 'integrations.fcm_credentials',
        'google_maps_key' => 'integrations.google_maps_key',
    ];

    public function key(): string
    {
        return 'integrations';
    }

    public function label(): string
    {
        return __('API keys');
    }

    public function description(): string
    {
        return __('Optional third-party keys. Every one of them is optional: with none set, the platform still boots, browses, books and takes cash.');
    }

    public function keys(): array
    {
        return [
            'integrations.fcm_credentials',
            'integrations.google_maps_key',
        ];
    }

    public function rules(array $input): array
    {
        return [
            // The Firebase service-account JSON. Pasted whole; validated as JSON
            // so a truncated paste is caught here rather than at the first push.
            'fcm_credentials' => ['nullable', 'string', 'json', 'max:8000'],
            'google_maps_key' => ['nullable', 'string', 'max:191'],
            'remove_fcm_credentials' => ['boolean'],
            'remove_google_maps_key' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $values = [];

        foreach (self::SECRETS as $field => $settingKey) {
            $values[$field.'_set'] = $this->settings->string($settingKey) !== '';
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        foreach (self::SECRETS as $field => $settingKey) {
            $submitted = $data[$field] ?? null;

            if ($this->toggle($data, 'remove_'.$field)) {
                $this->settings->set($settingKey, null);
            } elseif (is_string($submitted) && $submitted !== '') {
                $this->settings->set($settingKey, $submitted);
            }
        }
    }
}
