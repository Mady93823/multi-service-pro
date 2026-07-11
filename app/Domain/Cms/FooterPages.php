<?php

namespace App\Domain\Cms;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cached title+slug list behind the storefront footer (white-label rule:
 * footer links come from the pages table, never hardcoded). Shared on every
 * Inertia request, so it is cached forever and flushed by SavePage/DeletePage.
 */
class FooterPages
{
    private const CACHE_KEY = 'cms.footer_pages';

    /**
     * @return list<array{title: string, slug: string}>
     */
    public function all(): array
    {
        try {
            /** @var list<array{title: string, slug: string}> */
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                return Page::query()
                    ->inFooter()
                    ->get(['title', 'slug'])
                    ->map(fn (Page $page): array => ['title' => $page->title, 'slug' => $page->slug])
                    ->all();
            });
        } catch (Throwable) {
            // Table missing (installer, early boot) — an empty footer beats a 500.
            return [];
        }
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
