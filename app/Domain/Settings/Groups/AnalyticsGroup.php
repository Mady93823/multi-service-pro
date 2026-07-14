<?php

namespace App\Domain\Settings\Groups;

/**
 * Analytics IDs are typed fields that render a **known** snippet — deliberately
 * not the custom-JS panel (D26). The common case ("add GA4") must never require
 * an admin to hold the site-wide script-execution permission.
 */
class AnalyticsGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'analytics';
    }

    public function label(): string
    {
        return __('Analytics');
    }

    public function description(): string
    {
        return __('Measurement IDs only. Tags load on the storefront, and never before a visitor has accepted the cookie banner.');
    }

    public function keys(): array
    {
        return [
            'analytics.ga4_id',
            'analytics.gtm_id',
            'analytics.meta_pixel_id',
        ];
    }

    public function rules(array $input): array
    {
        return [
            // Shapes, not free text: the value is interpolated into a script we
            // ship, so it may only ever be an id.
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,20}$/'],
            'meta_pixel_id' => ['nullable', 'string', 'regex:/^\d{5,20}$/'],
        ];
    }

    public function values(): array
    {
        return [
            'ga4_id' => $this->settings->string('analytics.ga4_id'),
            'gtm_id' => $this->settings->string('analytics.gtm_id'),
            'meta_pixel_id' => $this->settings->string('analytics.meta_pixel_id'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('analytics.ga4_id', $data['ga4_id'] ?? null);
        $this->settings->set('analytics.gtm_id', $data['gtm_id'] ?? null);
        $this->settings->set('analytics.meta_pixel_id', $data['meta_pixel_id'] ?? null);
    }
}
