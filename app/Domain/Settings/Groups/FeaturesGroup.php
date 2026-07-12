<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Http\Request;

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

    public function rules(Request $request): array
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
