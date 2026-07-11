<?php

use App\Models\Page;
use Inertia\Testing\AssertableInertia;

it('renders a published page with sanitized html', function () {
    $page = Page::factory()->create([
        'title' => 'Service Guarantee',
        'slug' => 'service-guarantee',
        'body' => "## Our promise\n\n<script>alert(1)</script>We fix it.",
    ]);

    $this->get('/p/'.$page->slug)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('cms/show')
            ->where('page.title', 'Service Guarantee')
            ->where('page.html', fn ($html): bool => str_contains((string) $html, '<h2>Our promise</h2>')
                && ! str_contains((string) $html, '<script')));
});

it('404s an unpublished page', function () {
    $page = Page::factory()->unpublished()->create();

    $this->get('/p/'.$page->slug)->assertNotFound();
});

it('404s an unknown slug', function () {
    $this->get('/p/not-a-page')->assertNotFound();
});

it('shares only published footer pages with the storefront', function () {
    $footer = Page::factory()->inFooter()->create(['title' => 'Refund Policy']);
    $draftFooter = Page::factory()->inFooter()->unpublished()->create();
    $plain = Page::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('footer_pages', function ($pages) use ($footer, $draftFooter, $plain): bool {
                $slugs = collect($pages)->pluck('slug');

                return $slugs->contains($footer->slug)
                    && ! $slugs->contains($draftFooter->slug)
                    && ! $slugs->contains($plain->slug);
            }));
});
