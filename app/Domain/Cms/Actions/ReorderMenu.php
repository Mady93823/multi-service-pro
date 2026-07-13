<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\SiteMenus;
use App\Models\Menu;
use App\Models\MenuItem;

class ReorderMenu
{
    public function __construct(private SiteMenus $menus) {}

    /**
     * @param  list<int>  $ids  item ids in their new order
     */
    public function handle(Menu $menu, array $ids): void
    {
        // Scope to the menu: an id from another menu would otherwise be
        // reordered — and silently stolen — by whoever posts it.
        $items = $menu->items()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $position => $id) {
            $item = $items->get($id);

            if ($item instanceof MenuItem) {
                $item->update(['sort_order' => $position + 1]);
            }
        }

        $this->menus->flush();
    }
}
