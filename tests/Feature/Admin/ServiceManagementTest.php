<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;

test('admin can create a service with addons and related links', function () {
    $category = Category::factory()->create();
    $related = Service::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.services.store'), [
            'category_id' => $category->id,
            'name' => 'Sofa Cleaning',
            'short_description' => 'Five-seat sofa shampooing.',
            'pricing_type' => 'fixed',
            'price' => 599,
            'duration_minutes' => 60,
            'is_featured' => true,
            'is_active' => true,
            'addons' => [
                ['name' => 'Extra seat', 'price' => 99, 'is_active' => true],
            ],
            'related_ids' => [$related->id],
        ])
        ->assertRedirect(route('admin.services.index'));

    $service = Service::query()->where('slug', 'sofa-cleaning')->firstOrFail();

    expect($service->is_featured)->toBeTrue()
        ->and($service->addons)->toHaveCount(1)
        ->and($service->related->pluck('id')->all())->toBe([$related->id]);
});

test('updating a service replaces its addons wholesale', function () {
    $service = Service::factory()->create();
    $service->addons()->createMany([
        ['name' => 'Old addon A', 'price' => 10, 'is_active' => true],
        ['name' => 'Old addon B', 'price' => 20, 'is_active' => true],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.services.update', $service), [
            'category_id' => $service->category_id,
            'name' => $service->name,
            'pricing_type' => 'fixed',
            'price' => 100,
            'is_active' => true,
            'addons' => [
                ['name' => 'New addon', 'price' => 50, 'is_active' => true],
            ],
            'related_ids' => [],
        ])
        ->assertRedirect(route('admin.services.index'));

    expect($service->refresh()->addons->pluck('name')->all())->toBe(['New addon']);
});

test('a service cannot be related to itself', function () {
    $service = Service::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.services.update', $service), [
            'category_id' => $service->category_id,
            'name' => $service->name,
            'pricing_type' => 'fixed',
            'price' => 100,
            'is_active' => true,
            'related_ids' => [$service->id],
        ])
        ->assertSessionHasErrors('related_ids.0');
});

test('deleting a service soft-deletes and detaches cross-sell links', function () {
    $service = Service::factory()->create();
    $other = Service::factory()->create();
    $other->related()->attach($service);
    $service->related()->attach($other);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.services.destroy', $service))
        ->assertRedirect(route('admin.services.index'));

    expect(Service::query()->find($service->id))->toBeNull()
        ->and(Service::withTrashed()->find($service->id))->not->toBeNull()
        ->and($other->refresh()->related)->toHaveCount(0);
});

test('non-admins cannot manage services', function () {
    $provider = User::factory()->provider()->create();

    $this->actingAs($provider)->get(route('admin.services.index'))->assertForbidden();
    $this->actingAs($provider)->post(route('admin.services.store'), [])->assertForbidden();
});
