<?php

namespace App\Domain\Settings\Groups;

use App\Support\BrandMark;
use Illuminate\Http\UploadedFile;
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
        return __('Name, logo, favicon and accent color shown across the platform.');
    }

    public function keys(): array
    {
        return ['branding.app_name', 'branding.primary_color', 'branding.logo_path', 'branding.favicon_path'];
    }

    public function rules(array $input): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
            // The favicon is the one image a browser renders at 16px, so ICO and
            // SVG belong here even though no other upload in the product takes them.
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg,webp', 'max:512'],
            'remove_favicon' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $logoPath = $this->settings->string('branding.logo_path');
        $faviconPath = $this->settings->string('branding.favicon_path');

        return [
            'app_name' => $this->settings->string('branding.app_name', (string) config('app.name')),
            'primary_color' => $this->settings->string('branding.primary_color') ?: null,
            'logo_url' => $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null,
            'favicon_url' => $faviconPath !== '' ? Storage::disk('public')->url($faviconPath) : null,
            // So the form can show what the theme falls back to when the colour
            // is cleared, rather than a swatch of black that means nothing.
            'default_color' => BrandMark::DEFAULT_COLOR,
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('branding.app_name', $data['app_name']);
        $this->settings->set('branding.primary_color', $data['primary_color'] ?? null);

        $this->storeImage($data, $files, 'logo', 'branding.logo_path');
        $this->storeImage($data, $files, 'favicon', 'branding.favicon_path');
    }

    /**
     * Blank means "keep", `remove_*` means "erase" — the same contract the
     * write-only gateway secrets use (M08), so every admin form behaves alike.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $files
     */
    private function storeImage(array $data, array $files, string $field, string $key): void
    {
        $upload = $files[$field] ?? null;
        $current = $this->settings->string($key);

        if ($upload !== null) {
            $path = $upload->store('branding', 'public');
            $this->settings->set($key, $path !== false ? $path : null);
            $this->deleteFile($current);

            return;
        }

        if ($this->toggle($data, 'remove_'.$field) && $current !== '') {
            $this->settings->set($key, null);
            $this->deleteFile($current);
        }
    }

    private function deleteFile(string $path): void
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
