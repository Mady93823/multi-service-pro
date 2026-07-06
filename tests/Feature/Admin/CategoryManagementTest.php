<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;

test('admin can create a category with an auto-generated slug', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), [
            'name' => 'Pest Control',
            'parent_id' => null,
            'sort_order' => 3,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', ['name' => 'Pest Control', 'slug' => 'pest-control', 'sort_order' => 3]);
});

test('duplicate names get suffixed slugs', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Gardening', 'is_active' => true]);
    $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Gardening', 'is_active' => true]);

    expect(Category::query()->where('slug', 'gardening')->exists())->toBeTrue()
        ->and(Category::query()->where('slug', 'gardening-2')->exists())->toBeTrue();
});

test('a sub-category cannot be used as a parent (two-level tree)', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), [
            'name' => 'Too Deep',
            'parent_id' => $child->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a category cannot become its own parent', function () {
    $category = Category::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $category->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('renaming a category keeps its slug stable', function () {
    $category = Category::factory()->create(['name' => 'Original', 'slug' => 'original']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.categories.update', $category), [
            'name' => 'Renamed',
            'parent_id' => null,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->refresh())
        ->name->toBe('Renamed')
        ->slug->toBe('original');
});

test('deleting a category with services is blocked', function () {
    $category = Category::factory()->create();
    Service::factory()->create(['category_id' => $category->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertSessionHasErrors('category');

    expect(Category::query()->find($category->id))->not->toBeNull();
});

test('deleting an empty category soft-deletes it', function () {
    $category = Category::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::query()->find($category->id))->toBeNull()
        ->and(Category::withTrashed()->find($category->id))->not->toBeNull();
});

test('non-admins cannot manage categories', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($customer)->post(route('admin.categories.store'), ['name' => 'Nope'])->assertForbidden();
});
