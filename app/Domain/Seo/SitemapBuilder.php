<?php

namespace App\Domain\Seo;

use App\Domain\Catalog\Enums\CategoryType;
use App\Domain\Settings\SettingsRegistry;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;

/**
 * Every public URL, and nothing else (M24).
 *
 * Membership is decided by the same scopes the storefront itself uses — a
 * scheduled blog post is absent until it is live (M21), an unpublished page is
 * absent, an inactive service is absent. There is no second definition of
 * "public" to drift out of sync, which is the only way a sitemap stays honest.
 *
 * Cached, because a crawler hits it far more often than the catalog changes.
 */
class SitemapBuilder
{
    private const CACHE_KEY = 'seo.sitemap';

    private const TTL_MINUTES = 60;

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function enabled(): bool
    {
        return $this->settings->boolean('seo.sitemap_enabled', true);
    }

    /**
     * @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function urls(): array
    {
        /** @var list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}> $urls */
        $urls = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::TTL_MINUTES), fn (): array => $this->build());

        return $urls;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function build(): array
    {
        $urls = [
            $this->url(url('/'), null, 'daily', '1.0'),
            $this->url(route('catalog.index'), null, 'daily', '0.9'),
        ];

        // The events surface is listed only when something lives on it — the
        // sitemap walks what the storefront actually shows.
        if (Category::query()->where('is_active', true)->ofType(CategoryType::Event)->exists()) {
            $urls[] = $this->url(route('events.index'), null, 'daily', '0.8');
        }

        foreach (Category::query()->where('is_active', true)->get() as $category) {
            $urls[] = $this->url(route('catalog.category', $category->slug), $category->updated_at?->toAtomString(), 'weekly', '0.7');
        }

        foreach (Service::query()->where('is_active', true)->with('category')->get() as $service) {
            if ($service->category === null) {
                continue;
            }

            $urls[] = $this->url(
                route('catalog.show', [$service->category->slug, $service->slug]),
                $service->updated_at?->toAtomString(),
                'weekly',
                '0.8',
            );
        }

        // The reserved `home` page is served at `/` and 404s at /p/home (M20) —
        // listing it here would publish a URL that does not exist.
        foreach (Page::query()->where('is_published', true)->where('slug', '!=', Page::HOME_SLUG)->get() as $page) {
            $urls[] = $this->url(route('pages.show', $page->slug), $page->updated_at?->toAtomString(), 'monthly', '0.5');
        }

        if ($this->settings->boolean('blog.enabled', true)) {
            $urls[] = $this->url(route('blog.index'), null, 'weekly', '0.6');

            foreach (BlogPost::query()->published()->get() as $post) {
                $urls[] = $this->url(route('blog.show', $post->slug), $post->updated_at?->toAtomString(), 'monthly', '0.6');
            }
        }

        return $urls;
    }

    /**
     * @return array{loc: string, lastmod: ?string, changefreq: string, priority: string}
     */
    private function url(string $loc, ?string $lastmod, string $changefreq, string $priority): array
    {
        return ['loc' => $loc, 'lastmod' => $lastmod, 'changefreq' => $changefreq, 'priority' => $priority];
    }
}
