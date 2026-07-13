<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Support\Collection;

/**
 * Every location has exactly one menu, created on first sight. The admin never
 * creates a menu — it manages the items of a location that already exists, so
 * the storefront can never point at a location nothing owns.
 */
class EnsureMenus
{
    /**
     * @return Collection<string, Menu> keyed by location
     */
    public function handle(): Collection
    {
        /** @var Collection<string, Menu> $menus */
        $menus = new Collection;

        foreach (MenuLocation::cases() as $location) {
            $menus[$location->value] = Menu::query()->firstOrCreate(
                ['location' => $location->value],
                ['name' => $location->label()],
            );
        }

        return $menus;
    }
}
