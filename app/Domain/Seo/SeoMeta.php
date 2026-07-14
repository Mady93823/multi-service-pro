<?php

namespace App\Domain\Seo;

use App\Domain\Settings\SettingsRegistry;

/**
 * The meta block every public page hands to `<SeoHead>` (M24).
 *
 * Three layers, most specific first: the record's own `meta_title` /
 * `meta_description`, then its natural title and summary, then the site-wide
 * defaults. A blank field never becomes a blank `<title>` — it falls through.
 */
class SeoMeta
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array{title: string, description: ?string, image: ?string, url: string, type: string, site_name: string}
     */
    public function resolve(
        string $url,
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        string $type = 'website',
    ): array {
        $siteName = $this->settings->string('branding.app_name', (string) config('app.name'));

        return [
            'title' => $this->firstFilled([$title, $this->settings->string('seo.meta_title'), $siteName]) ?? $siteName,
            'description' => $this->firstFilled([$description, $this->settings->string('seo.meta_description')]),
            'image' => $this->firstFilled([$image, $this->settings->string('seo.og_image_url')]),
            'url' => $url,
            'type' => $type,
            'site_name' => $siteName,
        ];
    }

    /**
     * @param  list<string|null>  $candidates
     */
    private function firstFilled(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
