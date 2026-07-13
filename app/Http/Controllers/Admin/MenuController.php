<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\Actions\DeleteMenuItem;
use App\Domain\Cms\Actions\EnsureMenus;
use App\Domain\Cms\Actions\ReorderMenu;
use App\Domain\Cms\Actions\SaveMenuItem;
use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderMenuRequest;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(EnsureMenus $ensure): Response
    {
        $menus = $ensure->handle()
            ->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'location' => $menu->location->value,
                'label' => $menu->location->label(),
                'items' => $menu->rootItems()
                    ->with('children')
                    ->get()
                    ->map(fn (MenuItem $item): array => $this->item($item))
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/menus/index', [
            'menus' => $menus,
            'types' => array_map(
                fn (MenuItemType $type): array => ['value' => $type->value, 'label' => $type->label()],
                MenuItemType::cases(),
            ),
            'visibilities' => array_map(
                fn (MenuVisibility $visibility): array => ['value' => $visibility->value, 'label' => $visibility->label()],
                MenuVisibility::cases(),
            ),
            'routes' => array_map(
                fn (string $name, string $label): array => ['value' => $name, 'label' => $label],
                array_keys(MenuItemType::allowedRoutes()),
                array_values(MenuItemType::allowedRoutes()),
            ),
            'pages' => Page::query()
                ->where('is_published', true)
                ->orderBy('title')
                ->get(['title', 'slug'])
                ->map(fn (Page $page): array => ['value' => $page->slug, 'label' => $page->title])
                ->all(),
        ]);
    }

    public function store(StoreMenuItemRequest $request, Menu $menu, SaveMenuItem $action): RedirectResponse
    {
        $action->handle($menu, $request->validated());

        return back()->with('success', __('Menu item added.'));
    }

    public function update(StoreMenuItemRequest $request, Menu $menu, MenuItem $item, SaveMenuItem $action): RedirectResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $action->handle($menu, $request->validated(), $item);

        return back()->with('success', __('Menu item updated.'));
    }

    public function destroy(Menu $menu, MenuItem $item, DeleteMenuItem $action): RedirectResponse
    {
        abort_unless($item->menu_id === $menu->id, 404);

        $action->handle($item);

        return back()->with('success', __('Menu item deleted.'));
    }

    public function reorder(ReorderMenuRequest $request, Menu $menu, ReorderMenu $action): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated()['ids'];

        $action->handle($menu, $ids);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function item(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'label' => $item->label,
            'type' => $item->type->value,
            'target' => $item->target,
            'visibility' => $item->visibility->value,
            'is_active' => $item->is_active,
            'parent_id' => $item->parent_id,
            'children' => $item->children->map(fn (MenuItem $child): array => $this->item($child))->all(),
        ];
    }
}
