<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use Tests\Support\SettingsFixtures;

/**
 * M24: one meta resolver, one sitemap, one robots.txt — all generated from the
 * same definition of "public" the storefront itself uses.
 */
function seoService(): Service
{
    $category = Category::factory()->create(['is_active' => true]);

    return Service::factory()->for($category)->create(['is_active' => true]);
}

test('a page with no meta of its own falls back to the site defaults', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('seo.meta_description', 'Home services, done well.');

    $page = Page::factory()->create(['is_published' => true, 'meta_title' => null, 'meta_description' => null]);

    $this->get(route('pages.show', $page->slug))
        ->assertOk()
        ->assertInertia(fn ($page_) => $page_
            ->where('meta.title', $page->title)
            ->where('meta.description', 'Home services, done well.'));
});

test('a page with its own meta wins over the defaults', function () {
    app(SettingsRegistry::class)->set('seo.meta_title', 'Default title');

    $page = Page::factory()->create([
        'is_published' => true,
        'meta_title' => 'Custom title',
        'meta_description' => 'Custom description',
    ]);

    $this->get(route('pages.show', $page->slug))
        ->assertInertia(fn ($p) => $p
            ->where('meta.title', 'Custom title')
            ->where('meta.description', 'Custom description'));
});

test('the sitemap lists what is public and nothing else', function () {
    $service = seoService();
    $hidden = Service::factory()->for($service->category)->create(['is_active' => false]);

    $published = Page::factory()->create(['is_published' => true]);
    $draft = Page::factory()->create(['is_published' => false]);

    $live = BlogPost::factory()->create();
    $scheduled = BlogPost::factory()->scheduled()->create();

    $response = $this->get('/sitemap.xml')->assertOk();

    $response->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();

    expect($xml)->toContain(route('catalog.show', [$service->category->slug, $service->slug]))
        ->and($xml)->toContain(route('pages.show', $published->slug))
        ->and($xml)->toContain(route('blog.show', $live->slug))
        // An inactive service, a draft page and a scheduled post are not public,
        // so they are not in the sitemap either — one definition, not two.
        ->and($xml)->not->toContain($hidden->slug)
        ->and($xml)->not->toContain(route('pages.show', $draft->slug))
        ->and($xml)->not->toContain(route('blog.show', $scheduled->slug));
});

test('the home page is never listed at its /p/home URL', function () {
    $xml = (string) $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.url('/').'</loc>')
        ->and($xml)->not->toContain('/p/home');
});

test('a switched-off sitemap 404s rather than serving an empty one', function () {
    app(SettingsRegistry::class)->set('seo.sitemap_enabled', false);

    // An empty <urlset> would tell a crawler the site has no pages at all.
    $this->get('/sitemap.xml')->assertNotFound();

    expect((string) $this->get('/robots.txt')->getContent())->not->toContain('Sitemap:');
});

test('robots.txt is generated, keeps the panels out, and carries the admin extras', function () {
    app(SettingsRegistry::class)->set('seo.robots_extra', 'Disallow: /secret-campaign');

    $response = $this->get('/robots.txt')->assertOk();

    $body = (string) $response->getContent();

    expect($body)->toContain('Disallow: /admin')
        ->and($body)->toContain('Disallow: /provider')
        ->and($body)->toContain('Sitemap: '.url('/sitemap.xml'))
        ->and($body)->toContain('Disallow: /secret-campaign');
});

test('structured data is attached to the home page and can be switched off', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('schema.@type', 'LocalBusiness'));

    app(SettingsRegistry::class)->set('seo.schema_enabled', false);

    $this->get('/')->assertInertia(fn ($page) => $page->where('schema', null));
});

test('a service page carries Service structured data and its own meta', function () {
    $service = seoService();

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('schema.@type', 'Service')
            ->where('schema.offers.priceCurrency', 'INR')
            ->where('meta.title', $service->name));
});

test('a blog post carries Article structured data', function () {
    $post = BlogPost::factory()->create();

    $this->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('schema.@type', 'Article')
            ->where('meta.type', 'article'));
});

test('an admin sets the SEO defaults, and only an admin can', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'seo'), SettingsFixtures::payload('seo', [
            'meta_title' => 'UrbanServe — home services',
            'meta_description' => 'Book a professional in minutes.',
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->string('seo.meta_title'))->toBe('UrbanServe — home services');

    $this->actingAs(User::factory()->customer()->create())
        ->put(route('admin.settings.update', 'seo'), SettingsFixtures::payload('seo'))
        ->assertForbidden();
});
