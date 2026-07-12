<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'branding';
    }

    public function label(): string
    {
        return __('Branding');
    }

    public function description(): string
    {
        return __('Name, logo and accent color shown across the platform.');
    }

    public function keys(): array
    {
        return ['branding.app_name', 'branding.primary_color', 'branding.logo_path'];
    }

    public function rules(Request $request): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $logoPath = $this->settings->string('branding.logo_path');

        return [
            'app_name' => $this->settings->string('branding.app_name', (string) config('app.name')),
            'primary_color' => $this->settings->string('branding.primary_color') ?: null,
            'logo_url' => $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null,
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('branding.app_name', $data['app_name']);
        $this->settings->set('branding.primary_color', $data['primary_color'] ?? null);

        $logo = $files['logo'] ?? null;
        $currentLogo = $this->settings->string('branding.logo_path');

        if ($logo !== null) {
            $path = $logo->store('branding', 'public');
            $this->settings->set('branding.logo_path', $path !== false ? $path : null);
            $this->deleteFile($currentLogo);
        } elseif ($this->toggle($data, 'remove_logo') && $currentLogo !== '') {
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
