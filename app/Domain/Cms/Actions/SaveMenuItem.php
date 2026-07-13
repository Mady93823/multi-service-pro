<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\SiteMenus;
use App\Models\Menu;
use App\Models\MenuItem;

class SaveMenuItem
{
    public function __construct(private SiteMenus $menus) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Menu $menu, array $data, ?MenuItem $item = null): MenuItem
    {
        $parentId = $data['parent_id'] ?? null;

        // A child of an item in another menu would render under a heading it
        // does not belong to; an item parented to itself would recurse forever.
        if ($parentId !== null) {
            $parent = MenuItem::query()->find($parentId);

            if ($parent === null
                || $parent->menu_id !== $menu->id
                || ($item !== null && $parent->id === $item->id)
                || $parent->parent_id !== null) {
                $parentId = null;
            }
        }

        $attributes = [
            'label' => $data['label'],
            'type' => $data['type'],
            'target' => $data['target'] ?? null,
            'visibility' => $data['visibility'] ?? 'everyone',
            'parent_id' => $parentId,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($item === null) {
            $attributes['sort_order'] = (int) $menu->items()->max('sort_order') + 1;

            $item = $menu->items()->create($attributes);
        } else {
            $item->update($attributes);
        }

        $this->menus->flush();

        return $item;
    }
}
