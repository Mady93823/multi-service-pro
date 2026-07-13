<?php

namespace App\Domain\Cms;

use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuLocation;
use App\Domain\Cms\Enums\MenuVisibility;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The storefront's menus, resolved to plain links (M19).
 *
 * Shared on every Inertia request, so the resolved tree is cached forever and
 * flushed by the menu actions. Visibility is *not* baked into the cache — it
 * is filtered per request, because one cached tree serves guests and signed-in
 * users alike.
 *
 * Resolution is total: an item pointing at a deleted page, an unknown route or
 * a `javascript:` URL is **dropped**, never rendered. A broken menu item must
 * not be able to take the storefront down (or run script in it).
 */
class SiteMenus
{
    private const CACHE_KEY = 'cms.site_menus';

    /**
     * Resolved menus for one visitor, keyed by location.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function forUser(?User $user): array
    {
        $visible = [];

        foreach ($this->resolved() as $location => $items) {
            $visible[$location] = $this->filter($items, $user);
        }

        return $visible;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filter(array $items, ?User $user): array
    {
        $kept = [];

        foreach ($items as $item) {
            $visibility = MenuVisibility::tryFrom((string) $item['visibility']) ?? MenuVisibility::Everyone;

            if (! $visibility->allows($user)) {
                continue;
            }

            /** @var list<array<string, mixed>> $children */
            $children = $item['children'];

            $kept[] = [
                'label' => $item['label'],
                'url' => $item['url'],
                'children' => $this->filter($children, $user),
            ];
        }

        return $kept;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function resolved(): array
    {
        try {
            /** @var array<string, list<array<string, mixed>>> */
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());
        } catch (Throwable) {
            // Tables missing (installer, early boot) — an empty menu beats a 500.
            return [];
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function build(): array
    {
        /** @var list<string> $publishedSlugs */
        $publishedSlugs = Page::query()->where('is_published', true)->pluck('slug')->all();

        $menus = Menu::query()
            ->with(['rootItems' => fn ($query) => $query->active()->with(['children' => fn ($q) => $q->active()])])
            ->get();

        $built = [];

        foreach (MenuLocation::cases() as $location) {
            $built[$location->value] = [];
        }

        foreach ($menus as $menu) {
            $built[$menu->location->value] = $this->mapItems($menu->rootItems, $publishedSlugs);
        }

        return $built;
    }

    /**
     * @param  iterable<int, MenuItem>  $items
     * @param  list<string>  $publishedSlugs
     * @return list<array<string, mixed>>
     */
    private function mapItems(iterable $items, array $publishedSlugs): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $url = $this->url($item, $publishedSlugs);

            if ($url === null) {
                continue;
            }

            $mapped[] = [
                'label' => $item->label,
                'url' => $url,
                'visibility' => $item->visibility->value,
                'children' => $item->relationLoaded('children')
                    ? $this->mapItems($item->children, $publishedSlugs)
                    : [],
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<string>  $publishedSlugs
     */
    private function url(MenuItem $item, array $publishedSlugs): ?string
    {
        $target = (string) $item->target;

        return match ($item->type) {
            MenuItemType::Route => array_key_exists($target, MenuItemType::allowedRoutes())
                ? route($target, absolute: false)
                : null,
            MenuItemType::Page => in_array($target, $publishedSlugs, true)
                ? '/p/'.$target
                : null,
            // Same rule as banner links: an href is a script sink, so only
            // http(s) and site-relative paths survive.
            MenuItemType::Url => preg_match('#^(https?://|/)#', $target) === 1 ? $target : null,
        };
    }
}
