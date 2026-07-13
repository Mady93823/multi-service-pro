<?php

namespace App\Domain\Settings\Groups;

/**
 * Admin-authored CSS/JS injected into the storefront shell (ADR D26).
 *
 * This is, by design, a hole in the site's own XSS defences — an admin can run
 * script on every storefront page. It is therefore: off by default, admin-only,
 * storefront-only (never the admin or provider panel, so a bad snippet cannot
 * lock an admin out of the screen that would let them remove it), and audited
 * on every save.
 */
class CustomCodeGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'custom_code';
    }

    public function label(): string
    {
        return __('Custom CSS & JS');
    }

    public function description(): string
    {
        return __('Snippets injected into the storefront only — never the admin or provider panel. Off by default.');
    }

    public function keys(): array
    {
        return ['custom_code.enabled', 'custom_code.css', 'custom_code.js'];
    }

    public function rules(array $input): array
    {
        return [
            'enabled' => ['boolean'],
            'css' => ['nullable', 'string', 'max:20000'],
            'js' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function values(): array
    {
        return [
            'enabled' => $this->settings->boolean('custom_code.enabled'),
            'css' => $this->settings->string('custom_code.css') ?: null,
            'js' => $this->settings->string('custom_code.js') ?: null,
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('custom_code.enabled', $this->toggle($data, 'enabled'));
        $this->settings->set('custom_code.css', $data['css'] ?? null);
        $this->settings->set('custom_code.js', $data['js'] ?? null);
    }
}
