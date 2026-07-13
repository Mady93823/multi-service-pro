<?php

use App\Domain\Cms\Actions\EnsureMenus;
use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuLocation;
use App\Domain\Cms\Enums\MenuVisibility;
use App\Domain\Cms\SiteMenus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function menuAdmin(): User
{
    return User::factory()->admin()->create();
}

function headerMenu(): Menu
{
    return app(EnsureMenus::class)->handle()[MenuLocation::Header->value];
}

beforeEach(function () {
    // The suite runs against the seeded database and CmsSeeder ships a header
    // and two footer columns — assertions about "the header" would otherwise be
    // assertions about the demo content (landmine 6: scope every count).
    MenuItem::query()->delete();

    app(SiteMenus::class)->flush();
});

it('opens the menu screen with one menu per location', function () {
    $this->actingAs(menuAdmin())
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('admin/menus/index')
            ->has('menus', count(MenuLocation::cases()))
            ->has('routes')
            ->has('pages'));

    expect(Menu::query()->count())->toBe(count(MenuLocation::cases()));
});

it('adds a link and the storefront renders it', function () {
    $menu = headerMenu();

    $this->actingAs(menuAdmin())
        ->post(route('admin.menus.items.store', $menu), [
            'label' => 'All services',
            'type' => MenuItemType::Route->value,
            'target' => 'catalog.index',
            'visibility' => MenuVisibility::Everyone->value,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('site.menus.header.0.label', 'All services')
            ->where('site.menus.header.0.url', '/services'));
});

it('refuses a route name outside the allowlist and a script-sink URL', function () {
    $menu = headerMenu();
    $admin = menuAdmin();

    $this->actingAs($admin)
        ->post(route('admin.menus.items.store', $menu), [
            'label' => 'Admin',
            'type' => MenuItemType::Route->value,
            'target' => 'admin.dashboard',
            'visibility' => MenuVisibility::Everyone->value,
        ])
        ->assertSessionHasErrors('target');

    $this->actingAs($admin)
        ->post(route('admin.menus.items.store', $menu), [
            'label' => 'Gotcha',
            'type' => MenuItemType::Url->value,
            'target' => 'javascript:alert(1)',
            'visibility' => MenuVisibility::Everyone->value,
        ])
        ->assertSessionHasErrors('target');

    expect(MenuItem::query()->count())->toBe(0);
});

it('drops a link whose page is unpublished instead of rendering a dead link', function () {
    $page = Page::factory()->create(['slug' => 'careers', 'is_published' => true]);
    $menu = headerMenu();

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => 'Careers',
        'type' => MenuItemType::Page->value,
        'target' => 'careers',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('site.menus.header', 1));

    $page->update(['is_published' => false]);
    app(SiteMenus::class)->flush();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('site.menus.header', 0));
});

it('hides an inactive link and honours per-audience visibility', function () {
    $menu = headerMenu();

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => 'Hidden',
        'is_active' => false,
    ]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => 'Sign up',
        'type' => MenuItemType::Route->value,
        'target' => 'register',
        'visibility' => MenuVisibility::Guests->value,
    ]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'label' => 'My bookings',
        'type' => MenuItemType::Route->value,
        'target' => 'bookings.index',
        'visibility' => MenuVisibility::SignedIn->value,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('site.menus.header', 1)
            ->where('site.menus.header.0.label', 'Sign up'));

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('site.menus.header', 1)
            ->where('site.menus.header.0.label', 'My bookings'));
});

it('reorders a menu and ignores an id from another menu', function () {
    $menus = app(EnsureMenus::class)->handle();
    $header = $menus[MenuLocation::Header->value];
    $footer = $menus[MenuLocation::FooterOne->value];

    $first = MenuItem::factory()->create(['menu_id' => $header->id, 'label' => 'First', 'sort_order' => 1]);
    $second = MenuItem::factory()->create(['menu_id' => $header->id, 'label' => 'Second', 'sort_order' => 2]);
    $foreign = MenuItem::factory()->create(['menu_id' => $footer->id, 'label' => 'Foreign', 'sort_order' => 1]);

    $this->actingAs(menuAdmin())
        ->post(route('admin.menus.reorder', $header), ['ids' => [$second->id, $first->id, $foreign->id]])
        ->assertRedirect();

    expect($second->refresh()->sort_order)->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(2)
        // The foreign item was named in the payload but belongs to another menu.
        ->and($foreign->refresh()->sort_order)->toBe(1);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.menus.header.0.label', 'Second'));
});

it('deletes a link with its children', function () {
    $menu = headerMenu();
    $parent = MenuItem::factory()->create(['menu_id' => $menu->id]);
    $child = MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id]);

    $this->actingAs(menuAdmin())
        ->delete(route('admin.menus.items.destroy', [$menu, $parent]))
        ->assertRedirect();

    expect(MenuItem::query()->whereKey([$parent->id, $child->id])->count())->toBe(0);
});

it('refuses to touch an item that belongs to another menu', function () {
    $menus = app(EnsureMenus::class)->handle();
    $header = $menus[MenuLocation::Header->value];
    $footer = $menus[MenuLocation::FooterOne->value];

    $item = MenuItem::factory()->create(['menu_id' => $footer->id]);

    $this->actingAs(menuAdmin())
        ->delete(route('admin.menus.items.destroy', [$header, $item]))
        ->assertNotFound();

    expect(MenuItem::query()->whereKey($item->id)->exists())->toBeTrue();
});
