<?php

use App\Models\Category;
use App\Models\Service;
use Inertia\Testing\AssertableInertia;

test('guests can browse the storefront home page', function () {
    // The home page is built from blocks since M20; the catalog lives at /services.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('home')->has('blocks'));

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('catalog/index')->has('categories')->has('featured'));
});

test('category page lists only active services', function () {
    $category = Category::factory()->create();
    $active = Service::factory()->create(['category_id' => $category->id]);
    Service::factory()->inactive()->create(['category_id' => $category->id]);

    $this->get(route('catalog.category', $category->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/category')
            ->has('services.data', 1)
            ->where('services.data.0.id', $active->id));
});

test('inactive categories are not publicly visible', function () {
    $category = Category::factory()->inactive()->create();

    $this->get(route('catalog.category', $category->slug))->assertNotFound();
});

test('service detail page shows addons and related services', function () {
    $service = Service::factory()->create();
    $service->addons()->create(['name' => 'Addon', 'price' => 49, 'is_active' => true]);
    $related = Service::factory()->create();
    $service->related()->attach($related);

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/show')
            ->where('service.id', $service->id)
            ->has('service.addons', 1)
            ->has('service.related', 1));
});

test('inactive services 404 on the detail page', function () {
    $service = Service::factory()->inactive()->create();

    $this->get(route('catalog.show', [$service->category->slug, $service->slug]))->assertNotFound();
});

test('a service cannot be reached under the wrong category slug', function () {
    $service = Service::factory()->create();
    $otherCategory = Category::factory()->create();

    $this->get(route('catalog.show', [$otherCategory->slug, $service->slug]))->assertNotFound();
});

test('search finds services by name', function () {
    Service::factory()->create(['name' => 'Aquarium Glass Polishing', 'slug' => 'aquarium-glass-polishing']);

    $this->get(route('catalog.index', ['search' => 'Aquarium']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/index')
            ->has('results.data', 1)
            ->where('results.data.0.slug', 'aquarium-glass-polishing'));
});
