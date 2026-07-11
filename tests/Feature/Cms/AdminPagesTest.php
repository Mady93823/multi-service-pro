<?php

use App\Models\Page;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function cmsAdmin(): User
{
    return User::factory()->admin()->create();
}

it('lists pages for the admin', function () {
    Page::factory()->create(['title' => 'Careers']);

    $this->actingAs(cmsAdmin())
        ->get(route('admin.pages.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('admin/pages/index')
            ->has('pages.data')
            ->has('pages.meta'));
});

it('creates a page and derives a unique slug from the title', function () {
    $admin = cmsAdmin();

    $this->actingAs($admin)->post(route('admin.pages.store'), [
        'title' => 'Help Center',
        'slug' => '',
        'body' => '## Help',
        'is_published' => true,
        'show_in_footer' => false,
        'sort_order' => 0,
    ])->assertRedirect(route('admin.pages.index'));

    $this->actingAs($admin)->post(route('admin.pages.store'), [
        'title' => 'Help Center',
        'slug' => '',
        'body' => '## Help again',
        'is_published' => false,
        'show_in_footer' => false,
        'sort_order' => 0,
    ]);

    expect(Page::query()->where('slug', 'help-center')->exists())->toBeTrue()
        ->and(Page::query()->where('slug', 'help-center-2')->exists())->toBeTrue();
});

it('slugifies an explicit slug', function () {
    $this->actingAs(cmsAdmin())->post(route('admin.pages.store'), [
        'title' => 'Gift Cards',
        'slug' => 'gift-cards',
        'body' => 'Soon.',
        'is_published' => true,
        'show_in_footer' => true,
        'sort_order' => 4,
    ]);

    $page = Page::query()->where('slug', 'gift-cards')->firstOrFail();

    expect($page->show_in_footer)->toBeTrue()
        ->and($page->sort_order)->toBe(4);
});

it('updates a page', function () {
    $page = Page::factory()->create();

    $this->actingAs(cmsAdmin())->put(route('admin.pages.update', $page), [
        'title' => 'Renamed',
        'slug' => $page->slug,
        'body' => 'New body.',
        'is_published' => false,
        'show_in_footer' => false,
        'sort_order' => 9,
    ])->assertRedirect(route('admin.pages.index'));

    expect($page->refresh())
        ->title->toBe('Renamed')
        ->is_published->toBeFalse();
});

it('deletes a page and drops it from the footer', function () {
    $page = Page::factory()->inFooter()->create();

    // Warm the footer cache, then make sure deletion flushes it.
    $this->get('/');

    $this->actingAs(cmsAdmin())
        ->delete(route('admin.pages.destroy', $page))
        ->assertRedirect(route('admin.pages.index'));

    expect(Page::query()->whereKey($page->id)->exists())->toBeFalse();

    $this->get('/')->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('footer_pages', fn ($pages): bool => ! collect($pages)->pluck('slug')->contains($page->slug)));
});

it('validates the page payload', function () {
    $this->actingAs(cmsAdmin())
        ->from(route('admin.pages.create'))
        ->post(route('admin.pages.store'), ['title' => '', 'body' => ''])
        ->assertSessionHasErrors(['title', 'body']);
});

it('blocks non-admins from managing pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.pages.index'))
        ->assertForbidden();
});
