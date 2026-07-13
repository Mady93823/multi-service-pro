<?php

namespace App\Domain\Settings\Groups;

class AppearanceGroup extends SettingsGroup
{
    /** @var list<string> */
    public const HEADER_VARIANTS = ['classic', 'centered', 'minimal'];

    /** @var list<string> */
    public const FOOTER_VARIANTS = ['columns', 'simple'];

    public function key(): string
    {
        return 'appearance';
    }

    public function label(): string
    {
        return __('Appearance');
    }

    public function description(): string
    {
        return __('Storefront header and footer style, footer content, and the look of the login page.');
    }

    public function keys(): array
    {
        return [
            'appearance.header_variant',
            'appearance.sticky_header',
            'appearance.footer_variant',
            'appearance.footer_about',
            'appearance.copyright',
            'appearance.contact_email',
            'appearance.contact_phone',
            'appearance.contact_address',
            'appearance.login_headline',
            'appearance.login_subcopy',
            'appearance.login_image_url',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'header_variant' => ['required', 'string', 'in:'.implode(',', self::HEADER_VARIANTS)],
            'sticky_header' => ['boolean'],
            'footer_variant' => ['required', 'string', 'in:'.implode(',', self::FOOTER_VARIANTS)],
            'footer_about' => ['nullable', 'string', 'max:500'],
            'copyright' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:300'],
            'login_headline' => ['nullable', 'string', 'max:120'],
            'login_subcopy' => ['nullable', 'string', 'max:300'],
            // Picked in the media library, stored as a URL: settings have no
            // model for medialibrary to hang a copy off (D29). A deleted asset
            // therefore degrades to the plain login page, never to a 500.
            'login_image_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    public function values(): array
    {
        return [
            'header_variant' => $this->settings->string('appearance.header_variant', 'classic'),
            'sticky_header' => $this->settings->boolean('appearance.sticky_header', true),
            'footer_variant' => $this->settings->string('appearance.footer_variant', 'columns'),
            'footer_about' => $this->settings->string('appearance.footer_about') ?: null,
            'copyright' => $this->settings->string('appearance.copyright') ?: null,
            'contact_email' => $this->settings->string('appearance.contact_email') ?: null,
            'contact_phone' => $this->settings->string('appearance.contact_phone') ?: null,
            'contact_address' => $this->settings->string('appearance.contact_address') ?: null,
            'login_headline' => $this->settings->string('appearance.login_headline') ?: null,
            'login_subcopy' => $this->settings->string('appearance.login_subcopy') ?: null,
            'login_image_url' => $this->settings->string('appearance.login_image_url') ?: null,
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('appearance.header_variant', $data['header_variant']);
        $this->settings->set('appearance.sticky_header', $this->toggle($data, 'sticky_header'));
        $this->settings->set('appearance.footer_variant', $data['footer_variant']);

        foreach (['footer_about', 'copyright', 'contact_email', 'contact_phone', 'contact_address', 'login_headline', 'login_subcopy', 'login_image_url'] as $field) {
            $this->settings->set('appearance.'.$field, $data[$field] ?? null);
        }
    }
}
