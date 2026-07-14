<?php

namespace App\Domain\Seo;

use App\Domain\Settings\SettingsRegistry;
use App\Models\BlogPost;
use App\Models\Service;

/**
 * schema.org JSON-LD (M24).
 *
 * The whole thing is one switch — `seo.schema_enabled` off returns null and the
 * storefront renders no script at all. Every value is a plain string or number
 * that gets JSON-encoded in the browser (never interpolated into markup), so an
 * admin-authored title cannot become a script tag.
 */
class SchemaBuilder
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array<string, mixed>|null
     */
    public function localBusiness(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $this->settings->string('branding.app_name', (string) config('app.name')),
            'url' => url('/'),
        ];

        $description = $this->settings->string('seo.meta_description');
        $image = $this->settings->string('seo.og_image_url');
        $phone = $this->settings->string('appearance.contact_phone');
        $email = $this->settings->string('appearance.contact_email');
        $address = $this->settings->string('appearance.contact_address');

        return array_filter($schema + [
            'description' => $description ?: null,
            'image' => $image ?: null,
            'telephone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => $address === '' ? null : [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function service(Service $service): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->name,
            'description' => $service->short_description,
            'url' => $service->category === null ? url('/') : route('catalog.show', [$service->category->slug, $service->slug]),
            'provider' => [
                '@type' => 'LocalBusiness',
                'name' => $this->settings->string('branding.app_name', (string) config('app.name')),
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (string) $service->price,
                'priceCurrency' => $this->settings->string('localization.currency', 'INR'),
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function article(BlogPost $post): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $post->coverUrl('hero'),
            'datePublished' => $post->published_at?->toIso8601String(),
            'author' => [
                '@type' => 'Organization',
                'name' => $this->settings->string('branding.app_name', (string) config('app.name')),
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    private function enabled(): bool
    {
        return $this->settings->boolean('seo.schema_enabled', true);
    }
}
