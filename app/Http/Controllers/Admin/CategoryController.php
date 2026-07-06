<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\CreateCategory;
use App\Domain\Catalog\Actions\DeleteCategory;
use App\Domain\Catalog\Actions\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::root()
            ->with(['children' => fn ($query) => $query->withCount('services')])
            ->withCount('services')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/categories/index', [
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/create', [
            'parents' => CategoryResource::collection(Category::root()->orderBy('sort_order')->get()),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['icon', 'image']),
            $request->file('icon'),
            $request->file('image'),
        );

        return to_route('admin.categories.index')->with('success', __('Category created.'));
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/edit', [
            'category' => new CategoryResource($category),
            'parents' => CategoryResource::collection(
                Category::root()->whereKeyNot($category->id)->orderBy('sort_order')->get()
            ),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): RedirectResponse
    {
        $action->handle(
            $category,
            $request->safe()->except(['icon', 'image']),
            $request->file('icon'),
            $request->file('image'),
        );

        return to_route('admin.categories.index')->with('success', __('Category updated.'));
    }

    public function destroy(Category $category, DeleteCategory $action): RedirectResponse
    {
        $action->handle($category);

        return to_route('admin.categories.index')->with('success', __('Category deleted.'));
    }
}
