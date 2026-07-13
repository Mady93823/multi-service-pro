<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\SiteMenus;
use App\Models\MenuItem;

class DeleteMenuItem
{
    public function __construct(private SiteMenus $menus) {}

    public function handle(MenuItem $item): void
    {
        // Children cascade at the database level (a sub-item without its parent
        // has nowhere to render).
        $item->delete();

        $this->menus->flush();
    }
}
