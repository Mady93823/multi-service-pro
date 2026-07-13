<?php

namespace App\Domain\Settings\Groups;

class SocialGroup extends SettingsGroup
{
    /** @var list<string> */
    public const NETWORKS = ['facebook', 'instagram', 'x', 'youtube', 'linkedin', 'whatsapp'];

    public function key(): string
    {
        return 'social';
    }

    public function label(): string
    {
        return __('Social links');
    }

    public function description(): string
    {
        return __('Profile links shown in the storefront footer. Leave a network blank to hide it.');
    }

    public function keys(): array
    {
        return array_map(fn (string $network): string => 'social.'.$network, self::NETWORKS);
    }

    public function rules(array $input): array
    {
        $rules = [];

        foreach (self::NETWORKS as $network) {
            // An href is a script sink: only http(s) survives (banner-link rule).
            $rules[$network] = ['nullable', 'string', 'max:255', 'url:http,https'];
        }

        return $rules;
    }

    public function values(): array
    {
        $values = [];

        foreach (self::NETWORKS as $network) {
            $values[$network] = $this->settings->string('social.'.$network) ?: null;
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        foreach (self::NETWORKS as $network) {
            $this->settings->set('social.'.$network, $data[$network] ?? null);
        }
    }
}
