<?php

use App\Domain\Blocks\BlockRegistry;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function builderAdmin(): User
{
    return User::factory()->admin()->create();
}

/** Test helpers are global in Pest — hence the prefix (MediaLibraryTest owns `libraryAsset`). */
function builderAsset(): MediaAsset
{
    Storage::fake('public');

    $asset = MediaAsset::query()->create(['name' => 'hero.png']);
    $asset->addMedia(UploadedFile::fake()->image('hero.png'))->toMediaCollection(MediaAsset::COLLECTION);

    return $asset;
}

function homePage(): Page
{
    return Page::query()->where('slug', Page::HOME_SLUG)->firstOrFail();
}

/**
 * @return list<array<string, mixed>>
 */
function renderedBlocks(string $url): array
{
    /** @var array{props: array{blocks?: list<array<string, mixed>>}} $page */
    $page = test()->get($url)->viewData('page');

    return $page['props']['blocks'] ?? [];
}

it('builds the storefront home page from the seeded blocks', function () {
    $types = collect(renderedBlocks('/'))->pluck('type');

    expect($types)->toContain('hero', 'categories_grid', 'services_grid', 'faq');

    $this->get('/')->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('home')->has('blocks'));
});

it('renders nothing for a block type the registry does not know, and never 500s', function () {
    homePage()->blocks()->create([
        'type' => 'ghost_block_from_a_removed_module',
        'payload' => ['heading' => 'Boo'],
        'sort_order' => 99,
        'is_active' => true,
    ]);

    $this->get('/')->assertOk();

    expect(collect(renderedBlocks('/'))->pluck('type'))->not->toContain('ghost_block_from_a_removed_module');
});

it('hides a block that is switched off or outside its window', function () {
    $home = homePage();
    $home->blocks()->delete();

    $home->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'Off', 'items' => []], 'sort_order' => 1, 'is_active' => false]);
    $home->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'Expired', 'items' => []], 'sort_order' => 2, 'ends_at' => now()->subDay()]);
    $home->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'Not yet', 'items' => []], 'sort_order' => 3, 'starts_at' => now()->addWeek()]);
    $home->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'Live', 'items' => []], 'sort_order' => 4]);

    $headings = collect(renderedBlocks('/'))->pluck('props.heading');

    expect($headings)->toContain('Live')
        ->and($headings)->not->toContain('Off', 'Expired', 'Not yet');
});

it('validates a payload against the schema of its own block type', function () {
    $home = homePage();
    $admin = builderAdmin();

    // A hero without a heading is a hero the renderer cannot draw.
    $this->actingAs($admin)
        ->post(route('admin.pages.blocks.store', $home), ['type' => 'hero', 'payload' => ['align' => 'center']])
        ->assertSessionHasErrors('payload.heading');

    // A type nobody registered cannot be stored at all.
    $this->actingAs($admin)
        ->post(route('admin.pages.blocks.store', $home), ['type' => 'wysiwyg_canvas', 'payload' => []])
        ->assertSessionHasErrors('type');

    // A script-sink link is refused the same way menus and banners refuse one.
    $this->actingAs($admin)
        ->post(route('admin.pages.blocks.store', $home), [
            'type' => 'cta',
            'payload' => ['heading' => 'Book now', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'],
        ])
        ->assertSessionHasErrors('payload.button_url');
});

it('only embeds a link whose host is on the allowlist', function () {
    $home = homePage();
    $admin = builderAdmin();

    $this->actingAs($admin)
        ->post(route('admin.pages.blocks.store', $home), [
            'type' => 'embed',
            'payload' => ['url' => 'https://example.test/not-a-video'],
        ])
        ->assertSessionHasErrors('payload.url');

    $this->actingAs($admin)
        ->post(route('admin.pages.blocks.store', $home), [
            'type' => 'embed',
            'payload' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ])
        ->assertSessionHasNoErrors();

    // The stored string is never what gets embedded — the src is derived.
    $embed = collect(renderedBlocks('/'))->firstWhere('type', 'embed');

    expect($embed['props']['src'])->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
});

it('renders markdown through the sanitizing renderer', function () {
    $home = homePage();
    $home->blocks()->delete();

    $this->actingAs(builderAdmin())
        ->post(route('admin.pages.blocks.store', $home), [
            'type' => 'rich_text',
            'payload' => ['body' => '**Bold** <script>alert(1)</script>', 'width' => 'narrow'],
        ])
        ->assertSessionHasNoErrors();

    $block = collect(renderedBlocks('/'))->firstWhere('type', 'rich_text');

    expect($block['props']['html'])->toBe("<p><strong>Bold</strong> alert(1)</p>\n");
});

it('copies a picked library picture into the block and counts it as used', function () {
    $home = homePage();
    $asset = builderAsset();

    $this->actingAs(builderAdmin())
        ->post(route('admin.pages.blocks.store', $home), [
            'type' => 'hero',
            'payload' => ['heading' => 'With a picture', 'align' => 'center', 'media_asset_id' => $asset->id],
        ])
        ->assertSessionHasNoErrors();

    $block = PageBlock::query()->where('type', 'hero')->latest('id')->firstOrFail();

    // Picking copies (D29): the block owns its own file, stamped with the asset.
    $media = $block->getFirstMedia(PageBlock::COLLECTION);

    expect($media)->not->toBeNull()
        ->and((int) $media->getCustomProperty(MediaAsset::USAGE_PROPERTY))->toBe($asset->id)
        ->and($asset->usageCount())->toBeGreaterThan(0);

    // And an asset in use cannot be deleted from the library.
    $this->actingAs(builderAdmin())
        ->delete(route('admin.media.destroy', $asset))
        ->assertSessionHasErrors();
});

it('drops a picture from the block when it leaves the payload', function () {
    $home = homePage();
    $asset = builderAsset();

    $this->actingAs(builderAdmin())->post(route('admin.pages.blocks.store', $home), [
        'type' => 'hero',
        'payload' => ['heading' => 'With a picture', 'align' => 'center', 'media_asset_id' => $asset->id],
    ]);

    $block = PageBlock::query()->where('type', 'hero')->latest('id')->firstOrFail();

    $this->actingAs(builderAdmin())->put(route('admin.pages.blocks.update', [$home, $block]), [
        'payload' => ['heading' => 'Without one', 'align' => 'center', 'media_asset_id' => null],
    ])->assertSessionHasNoErrors();

    expect($block->refresh()->getMedia(PageBlock::COLLECTION))->toHaveCount(0)
        ->and($asset->usageCount())->toBe(0);
});

it('reorders only the blocks of the page in the route', function () {
    $home = homePage();
    $home->blocks()->delete();

    $first = $home->blocks()->create(['type' => 'spacer', 'payload' => ['size' => 'sm'], 'sort_order' => 1]);
    $second = $home->blocks()->create(['type' => 'spacer', 'payload' => ['size' => 'lg'], 'sort_order' => 2]);

    $other = Page::factory()->create(['slug' => 'landing']);
    $foreign = $other->blocks()->create(['type' => 'spacer', 'payload' => ['size' => 'md'], 'sort_order' => 1]);

    $this->actingAs(builderAdmin())
        ->post(route('admin.pages.blocks.reorder', $home), ['ids' => [$second->id, $first->id, $foreign->id]])
        ->assertRedirect();

    // The foreign block is ignored, not stolen and not reordered.
    expect($second->refresh()->sort_order)->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(2)
        ->and($foreign->refresh()->sort_order)->toBe(1)
        ->and($foreign->page_id)->toBe($other->id);
});

it('duplicates a block to the end of its page', function () {
    $home = homePage();
    $home->blocks()->delete();

    $block = $home->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'Numbers', 'items' => []], 'sort_order' => 1]);

    $this->actingAs(builderAdmin())
        ->post(route('admin.pages.blocks.duplicate', [$home, $block]))
        ->assertRedirect();

    $blocks = $home->blocks()->get();

    expect($blocks)->toHaveCount(2)
        ->and($blocks->last()->payload)->toBe(['heading' => 'Numbers', 'items' => []])
        ->and($blocks->last()->sort_order)->toBe(2);
});

it('falls back to a usable storefront when the home page has no blocks', function () {
    homePage()->blocks()->delete();
    Service::query()->update(['is_featured' => true]);

    $types = collect(renderedBlocks('/'))->pluck('type');

    expect($types)->toContain('categories_grid', 'services_grid');
});

it('renders a page from its blocks when it has them, and from markdown when it does not', function () {
    $page = Page::factory()->create([
        'slug' => 'careers',
        'body' => '## We are hiring',
        'is_published' => true,
    ]);

    $this->get('/p/careers')
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('page.html', "<h2>We are hiring</h2>\n")
            ->has('blocks', 0));

    $page->blocks()->create(['type' => 'stats', 'payload' => ['heading' => 'By the numbers', 'items' => []], 'sort_order' => 1]);

    $this->get('/p/careers')
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('page.html', null)
            ->has('blocks', 1)
            ->where('blocks.0.props.heading', 'By the numbers'));
});

it('keeps the home page out of /p/ and out of the delete button', function () {
    $home = homePage();

    $this->get('/p/'.Page::HOME_SLUG)->assertNotFound();

    $this->actingAs(builderAdmin())
        ->delete(route('admin.pages.destroy', $home))
        ->assertSessionHasErrors();

    expect(Page::query()->whereKey($home->id)->exists())->toBeTrue();
});

it('gives every registered block type a schema the admin form can render', function () {
    $registry = app(BlockRegistry::class);

    expect($registry->types())->not->toBeEmpty();

    foreach ($registry->all() as $type => $block) {
        expect($block->type())->toBe($type)
            ->and($block->label())->not->toBeEmpty()
            // Every declared field is validated: a field with no rule is a field
            // an admin can fill with anything.
            ->and(array_keys($block->defaults()))
            ->each(fn ($field) => expect(array_keys($block->rules()))->toContain($field->value));
    }
});
