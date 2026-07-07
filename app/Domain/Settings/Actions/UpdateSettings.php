<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateSettings
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @param  array{app_name: string, primary_color?: string|null, currency: string, timezone: string, locale: string, remove_logo?: bool}  $data
     */
    public function handle(array $data, ?UploadedFile $logo = null): void
    {
        $this->settings->set('branding.app_name', $data['app_name']);
        $this->settings->set('branding.primary_color', $data['primary_color'] ?? null);
        $this->settings->set('localization.currency', $data['currency']);
        $this->settings->set('localization.timezone', $data['timezone']);
        $this->settings->set('localization.locale', $data['locale']);

        $currentLogo = $this->settings->string('branding.logo_path');

        if ($logo !== null) {
            $path = $logo->store('branding', 'public');
            $this->settings->set('branding.logo_path', $path !== false ? $path : null);
            $this->deleteFile($currentLogo);
        } elseif (($data['remove_logo'] ?? false) && $currentLogo !== '') {
            $this->settings->set('branding.logo_path', null);
            $this->deleteFile($currentLogo);
        }
    }

    private function deleteFile(string $path): void
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
