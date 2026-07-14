<?php

namespace App\Domain\Settings\Groups;

class SeoGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'seo';
    }

    public function label(): string
    {
        return __('SEO');
    }

    public function description(): string
    {
        return __('What search engines and shared links show when nothing more specific is set. Pages, services and posts may override the title and description.');
    }

    public function keys(): array
    {
        return [
            'seo.meta_title',
            'seo.meta_description',
            'seo.og_image_url',
            'seo.sitemap_enabled',
            'seo.schema_enabled',
            'seo.robots_extra',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:200'],
            // An OG image is an href a crawler fetches: same scheme rule as
            // every other admin-supplied URL (D30).
            'og_image_url' => ['nullable', 'string', 'max:2048'],
            'sitemap_enabled' => ['boolean'],
            'schema_enabled' => ['boolean'],
            // Appended verbatim to robots.txt, so it is capped and the file is
            // assembled by us — never echoed straight from the form.
            'robots_extra' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function values(): array
    {
        return [
            'meta_title' => $this->settings->string('seo.meta_title'),
            'meta_description' => $this->settings->string('seo.meta_description'),
            'og_image_url' => $this->settings->string('seo.og_image_url'),
            'sitemap_enabled' => $this->settings->boolean('seo.sitemap_enabled', true),
            'schema_enabled' => $this->settings->boolean('seo.schema_enabled', true),
            'robots_extra' => $this->settings->string('seo.robots_extra'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('seo.meta_title', $data['meta_title'] ?? null);
        $this->settings->set('seo.meta_description', $data['meta_description'] ?? null);
        $this->settings->set('seo.og_image_url', $data['og_image_url'] ?? null);
        $this->settings->set('seo.sitemap_enabled', $this->toggle($data, 'sitemap_enabled'));
        $this->settings->set('seo.schema_enabled', $this->toggle($data, 'schema_enabled'));
        $this->settings->set('seo.robots_extra', $data['robots_extra'] ?? null);
    }
}
