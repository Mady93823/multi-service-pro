<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SettingsFixtures;

function blogAdmin(): User
{
    return User::factory()->admin()->create();
}

/** Test helpers are global in Pest — hence the prefix (M18/M20 own theirs). */
function blogAsset(): MediaAsset
{
    Storage::fake('public');

    $asset = MediaAsset::query()->create(['name' => 'cover.png']);
    $asset->addMedia(UploadedFile::fake()->image('cover.png'))->toMediaCollection(MediaAsset::COLLECTION);

    return $asset;
}

beforeEach(function () {
    // The seeder ships demo posts — scope, never count globally.
    BlogPost::query()->delete();
});

it('lists published posts and hides drafts and scheduled ones', function () {
    BlogPost::factory()->create(['title' => 'Live post']);
    BlogPost::factory()->draft()->create(['title' => 'Still a draft']);
    BlogPost::factory()->scheduled()->create(['title' => 'Next week']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('blog/index')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Live post'));
});

it('404s a draft and a scheduled post, and serves a published one', function () {
    $draft = BlogPost::factory()->draft()->create();
    $scheduled = BlogPost::factory()->scheduled()->create();
    $live = BlogPost::factory()->create();

    $this->get(route('blog.show', $draft->slug))->assertNotFound();
    $this->get(route('blog.show', $scheduled->slug))->assertNotFound();
    $this->get(route('blog.show', $live->slug))->assertOk();
});

it('renders the body through the sanitizing markdown renderer', function () {
    $post = BlogPost::factory()->create(['body' => '**Bold** <script>alert(1)</script>']);

    $this->get(route('blog.show', $post->slug))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('html', "<p><strong>Bold</strong> alert(1)</p>\n")
            // The markdown source itself never reaches a public page.
            ->missing('post.body'));
});

it('falls back to the title and excerpt for the OG tags', function () {
    $post = BlogPost::factory()->create(['title' => 'Deep clean guide', 'excerpt' => 'How to prepare.', 'meta_title' => null]);
    $tagged = BlogPost::factory()->create(['meta_title' => 'Custom SEO title', 'meta_description' => 'Custom SEO copy.']);

    $this->get(route('blog.show', $post->slug))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('meta.title', 'Deep clean guide')
            ->where('meta.description', 'How to prepare.'));

    $this->get(route('blog.show', $tagged->slug))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('meta.title', 'Custom SEO title')
            ->where('meta.description', 'Custom SEO copy.'));
});

it('filters by category and by search term', function () {
    // The seeder ships its own categories — pick a slug it does not own.
    $category = BlogCategory::factory()->create(['name' => 'Guides', 'slug' => 'guides']);

    BlogPost::factory()->create(['title' => 'Deep cleaning tips', 'blog_category_id' => $category->id]);
    BlogPost::factory()->create(['title' => 'Company update', 'blog_category_id' => null]);

    $this->get(route('blog.index', ['category' => 'guides']))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Deep cleaning tips'));

    $this->get(route('blog.index', ['search' => 'Company']))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Company update'));
});

it('shows related posts from the same category, never the post itself', function () {
    $category = BlogCategory::factory()->create();

    $post = BlogPost::factory()->create(['blog_category_id' => $category->id]);
    $sibling = BlogPost::factory()->create(['blog_category_id' => $category->id]);
    BlogPost::factory()->create(['blog_category_id' => BlogCategory::factory()->create()->id]);

    $this->get(route('blog.show', $post->slug))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('related', 1)
            ->where('related.0.id', $sibling->id));
});

it('serves an RSS feed of published posts only', function () {
    BlogPost::factory()->create(['title' => 'Live post']);
    BlogPost::factory()->draft()->create(['title' => 'Still a draft']);

    $response = $this->get(route('blog.feed'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('<rss', false)
        ->assertSee('Live post')
        ->assertDontSee('Still a draft');
});

it('makes the whole blog disappear when it is switched off', function () {
    $post = BlogPost::factory()->create();

    $this->actingAs(blogAdmin())
        ->put(route('admin.settings.update', 'blog'), SettingsFixtures::payload('blog', ['blog_enabled' => false]))
        ->assertRedirect();

    $this->get(route('blog.index'))->assertNotFound();
    $this->get(route('blog.show', $post->slug))->assertNotFound();
    $this->get(route('blog.feed'))->assertNotFound();
});

it('creates a post with a cover picked from the library, deriving a unique slug', function () {
    $admin = blogAdmin();
    $asset = blogAsset();

    BlogPost::factory()->create(['title' => 'Taken', 'slug' => 'spring-cleaning']);

    $this->actingAs($admin)
        ->post(route('admin.blog.store'), [
            'title' => 'Spring cleaning',
            'body' => '## Hello',
            'excerpt' => 'A guide.',
            'tags' => ['home'],
            'is_published' => true,
            'media_asset_id' => $asset->id,
        ])
        ->assertRedirect(route('admin.blog.index'));

    $post = BlogPost::query()->where('title', 'Spring cleaning')->firstOrFail();

    expect($post->slug)->toBe('spring-cleaning-2')
        // Publishing without a date means "now" — the post is live immediately.
        ->and($post->published_at)->not->toBeNull()
        ->and($post->author_id)->toBe($admin->id)
        // Picking copies the file into the post (D29).
        ->and($post->getFirstMedia(BlogPost::COLLECTION))->not->toBeNull()
        ->and($asset->usageCount())->toBeGreaterThan(0);
});

it('keeps a scheduled post out of sight until its moment arrives', function () {
    $this->actingAs(blogAdmin())
        ->post(route('admin.blog.store'), [
            'title' => 'Coming soon',
            'body' => 'Later.',
            'is_published' => true,
            'published_at' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect();

    $post = BlogPost::query()->where('title', 'Coming soon')->firstOrFail();

    $this->get(route('blog.show', $post->slug))->assertNotFound();

    $this->travel(8)->days();

    $this->get(route('blog.show', $post->slug))->assertOk();
});

it('unpublishing a post clears its publication moment', function () {
    $post = BlogPost::factory()->create();

    $this->actingAs(blogAdmin())
        ->put(route('admin.blog.update', $post), [
            'title' => $post->title,
            'body' => $post->body,
            'is_published' => false,
        ])
        ->assertRedirect();

    expect($post->refresh()->published_at)->toBeNull();

    $this->get(route('blog.show', $post->slug))->assertNotFound();
});

it('deleting a category keeps its posts, uncategorised', function () {
    $category = BlogCategory::factory()->create();
    $post = BlogPost::factory()->create(['blog_category_id' => $category->id]);

    $this->actingAs(blogAdmin())
        ->delete(route('admin.blog.categories.destroy', $category))
        ->assertRedirect();

    expect(BlogPost::query()->whereKey($post->id)->exists())->toBeTrue()
        ->and($post->refresh()->blog_category_id)->toBeNull();

    $this->get(route('blog.show', $post->slug))->assertOk();
});

it('honours the posts-per-page setting', function () {
    BlogPost::factory()->count(5)->create();

    $this->actingAs(blogAdmin())
        ->put(route('admin.settings.update', 'blog'), SettingsFixtures::payload('blog', ['blog_posts_per_page' => 2]));

    $this->get(route('blog.index'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('posts.data', 2)
            ->where('posts.meta.total', 5));
});

it('lets a menu item point at the blog', function () {
    $menu = Menu::query()->firstOrFail();

    $this->actingAs(blogAdmin())
        ->post(route('admin.menus.items.store', $menu), [
            'label' => 'Blog',
            'type' => 'route',
            'target' => 'blog.index',
            'visibility' => 'everyone',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->get('/')->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->where('site.menus.header', fn (mixed $items): bool => collect($items)->contains(
            fn (mixed $item): bool => data_get($item, 'url') === '/blog',
        )));
});
