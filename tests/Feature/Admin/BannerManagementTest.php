<?php

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function bannerPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test banner',
        'link_url' => null,
        'placement' => 'home_hero',
        'sort_order' => 0,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
    ], $overrides);
}

test('customers cannot reach banner admin', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.banners.index'))
        ->assertForbidden();
});

test('an admin can create a banner with an image', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.banners.store'), bannerPayload())
        ->assertRedirect(route('admin.banners.index'));

    $banner = Banner::query()->where('title', 'Test banner')->sole();
    expect($banner->getMedia('image'))->toHaveCount(1);
});

test('the image is required when creating', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.banners.store'), bannerPayload(['image' => null]))
        ->assertSessionHasErrors('image');
});

test('a javascript: link is rejected — stored XSS guard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.banners.store'), bannerPayload(['link_url' => 'javascript:alert(1)']))
        ->assertSessionHasErrors('link_url');
});

test('updating without an image keeps the current one', function () {
    $banner = Banner::factory()->create();
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 800, 300))->toMediaCollection('image');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.banners.update', $banner), bannerPayload(['image' => null, 'title' => 'Renamed']))
        ->assertRedirect(route('admin.banners.index'));

    $banner->refresh();
    expect($banner->title)->toBe('Renamed')
        ->and($banner->getMedia('image'))->toHaveCount(1);
});

test('an admin can delete a banner', function () {
    $banner = Banner::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.banners.destroy', $banner))
        ->assertRedirect(route('admin.banners.index'));

    expect(Banner::query()->whereKey($banner->id)->exists())->toBeFalse();
});

test('the storefront home shows only live banners in their placement', function () {
    $live = Banner::factory()->create(['title' => 'Live hero now']);
    Banner::factory()->create(['title' => 'Switched off', 'is_active' => false]);
    Banner::factory()->scheduledOut()->create(['title' => 'Not yet scheduled']);
    Banner::factory()->strip()->create(['title' => 'Live strip now']);

    // Since M20 the home page is blocks, and each banner placement is one of
    // them (seeded on the reserved `home` page).
    /** @var array{props: array{blocks: list<array<string, mixed>>}} $page */
    $page = $this->get(route('home'))->viewData('page');

    $placement = fn (string $placement) => collect($page['props']['blocks'])
        ->firstWhere(fn (array $block): bool => $block['type'] === 'banners' && $block['props']['placement'] === $placement);

    $hero = collect($placement('home_hero')['props']['banners'])->pluck('title');
    $strip = collect($placement('home_strip')['props']['banners'])->pluck('title');

    expect($hero)->toContain('Live hero now')
        ->and($hero)->not->toContain('Switched off', 'Not yet scheduled', 'Live strip now')
        ->and($strip)->toContain('Live strip now');
});
