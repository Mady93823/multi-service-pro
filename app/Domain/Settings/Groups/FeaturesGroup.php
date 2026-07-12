<?php

namespace App\Domain\Settings\Groups;

class FeaturesGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return __('Features');
    }

    public function description(): string
    {
        return __('Platform-wide toggles.');
    }

    public function keys(): array
    {
        return ['features.otp_required'];
    }

    public function rules(array $input): array
    {
        return [
            'otp_required' => ['boolean'],
        ];
    }

    public function values(): array
    {
        return [
            'otp_required' => $this->settings->boolean('features.otp_required'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('features.otp_required', $this->toggle($data, 'otp_required'));
    }
}
