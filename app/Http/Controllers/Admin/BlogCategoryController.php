<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blog\Actions\DeleteBlogCategory;
use App\Domain\Blog\Actions\SaveBlogCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogCategoryRequest;
use App\Http\Resources\BlogCategoryResource;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlogCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/categories', [
            'categories' => BlogCategoryResource::collection(
                BlogCategory::query()->withCount('posts')->orderBy('sort_order')->orderBy('name')->get(),
            ),
        ]);
    }

    public function store(StoreBlogCategoryRequest $request, SaveBlogCategory $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', __('Category created.'));
    }

    public function update(StoreBlogCategoryRequest $request, BlogCategory $category, SaveBlogCategory $action): RedirectResponse
    {
        $action->handle($request->validated(), $category);

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(BlogCategory $category, DeleteBlogCategory $action): RedirectResponse
    {
        $action->handle($category);

        return back()->with('success', __('Category deleted. Its posts are now uncategorised.'));
    }
}
