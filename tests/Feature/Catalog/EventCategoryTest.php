<?php

use App\Domain\Catalog\Enums\CategoryType;
use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Seo\SitemapBuilder;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CheckoutFixtures;

// The seeded demo catalog already carries an "Event Management" root, so every
// assertion here is scoped to rows the test creates — never a bare count.

test('the events page lists event categories and the services page does not', function () {
    $event = Category::factory()->event()->create(['name' => 'Test Weddings']);
    $plain = Category::factory()->create(['name' => 'Test Plumbing']);

    $ids = fn (AssertableInertia $page): array => array_column($page->toArray()['props']['categories'], 'id');

    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($ids, $event, $plain) {
            $page->component('catalog/events');
            expect($ids($page))->toContain($event->id)->not->toContain($plain->id);
        });

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($ids, $event, $plain) {
            $page->component('catalog/index');
            expect($ids($page))->toContain($plain->id)->not->toContain($event->id);
        });
});

test('a featured event service surfaces on the events page, not among the services-page featured', function () {
    $eventRoot = Category::factory()->event()->create();
    $service = Service::factory()->create(['category_id' => $eventRoot->id, 'is_featured' => true]);

    $featuredIds = fn (AssertableInertia $page): array => array_column($page->toArray()['props']['featured'], 'id');

    $this->get(route('events.index'))
        ->assertInertia(function (AssertableInertia $page) use ($featuredIds, $service) {
            expect($featuredIds($page))->toContain($service->id);
        });

    $this->get(route('catalog.index'))
        ->assertInertia(function (AssertableInertia $page) use ($featuredIds, $service) {
            expect($featuredIds($page))->not->toContain($service->id);
        });
});

test('an event service books through the ordinary checkout', function () {
    [$customer, $address] = CheckoutFixtures::customer();
    $eventRoot = Category::factory()->event()->create();
    $service = Service::factory()->create(['category_id' => $eventRoot->id, 'price' => 2999]);

    // The category drill-down and detail pages are the ordinary catalog routes.
    $this->get(route('catalog.category', $eventRoot->slug))->assertOk();
    $this->get(route('catalog.show', [$eventRoot->slug, $service->slug]))->assertOk();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => CheckoutFixtures::slot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500004',
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    expect($booking->status->value)->toBe('placed')
        ->and($booking->items()->sole()->service_id)->toBe($service->id);
});

test('an admin can create an event category and a child inherits the surface', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Corporate Events',
        'type' => 'event',
        'is_active' => true,
    ])->assertRedirect(route('admin.categories.index'));

    $root = Category::query()->where('name', 'Corporate Events')->sole();
    expect($root->type->value)->toBe('event');

    $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Office Parties',
        'parent_id' => $root->id,
        'type' => 'service', // ignored: a child lives on its parent's surface
        'is_active' => true,
    ])->assertRedirect(route('admin.categories.index'));

    expect(Category::query()->where('name', 'Office Parties')->sole()->type->value)->toBe('event');
});

test('moving a root between surfaces carries its children with it', function () {
    $root = Category::factory()->create(['name' => 'Movable Root']);
    $child = Category::factory()->childOf($root)->create();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.categories.update', $root), [
        'name' => 'Movable Root',
        'type' => 'event',
        'is_active' => true,
    ])->assertRedirect(route('admin.categories.index'));

    expect($root->refresh()->type->value)->toBe('event')
        ->and($child->refresh()->type->value)->toBe('event');
});

test('the menu builder may link the events page', function () {
    expect(MenuItemType::allowedRoutes())->toHaveKey('events.index');
});

test('the sitemap lists the events page only while an active event category exists', function () {
    app(SitemapBuilder::class)->flush();
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('events.index'));

    Category::query()->ofType(CategoryType::Event)->update(['is_active' => false]);

    app(SitemapBuilder::class)->flush();
    $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('events.index'));
});
