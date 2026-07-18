<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\EventImageSeeder;
use Inertia\Testing\AssertableInertia;

test('the events hub is admin-only', function () {
    $this->get('/admin/events')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/events')->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get('/admin/events')->assertForbidden();
});

test('the hub lists event roots only, never the service catalog', function () {
    // CatalogSeeder always seeds the Event Management root — assert by slug,
    // never by count (landmine-6 family).
    $serviceRoot = Category::factory()->create(['name' => 'Ordinary Services Root']);
    $eventRoot = Category::factory()->create(['name' => 'Corporate Events', 'type' => 'event']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/events')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($serviceRoot, $eventRoot) {
            $page->component('admin/events/index')
                ->has('stats.categories')
                ->has('stats.services')
                ->has('stats.bookings_30')
                ->has('stats.revenue_30')
                ->has('recent');

            $slugs = collect($page->toArray()['props']['roots'])->pluck('slug');

            expect($slugs)->toContain('event-management')
                ->toContain($eventRoot->slug)
                ->not->toContain($serviceRoot->slug);
        });
});

test('event stats count only services under event categories', function () {
    $eventChild = Category::query()->where('slug', 'birthday-parties')->firstOrFail();
    Service::factory()->create(['category_id' => $eventChild->id, 'name' => 'Balloon Arch Install']);

    $expected = Service::query()
        ->whereHas('category', fn ($query) => $query->where('type', 'event'))
        ->count();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/events')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.services', $expected));
});

test('the image seeder is a no-op under tests', function () {
    // Parallel workers share storage/ (the lang/ landmine) — the seeder must
    // refuse to draw or attach anything while the suite runs.
    app(EventImageSeeder::class)->run();

    $root = Category::query()->where('slug', 'event-management')->firstOrFail();

    expect($root->image_path)->toBeNull();
});
