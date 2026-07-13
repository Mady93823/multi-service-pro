<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blog\Actions\DeletePost;
use App\Domain\Blog\Actions\SavePost;
use App\Http\Controllers\Concerns\ResolvesMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostRequest;
use App\Http\Resources\BlogCategoryResource;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    use ResolvesMediaAsset;

    public function index(Request $request): Response
    {
        $posts = BlogPost::query()
            ->with(['category', 'author', 'media'])
            ->search((string) $request->string('search'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/blog/index', [
            'posts' => BlogPostResource::collection($posts),
            'search' => (string) $request->string('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blog/create', [
            'categories' => BlogCategoryResource::collection($this->categories()),
        ]);
    }

    public function store(StoreBlogPostRequest $request, SavePost $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action->handle($request->validated(), $admin, $this->resolveAsset($request));

        return to_route('admin.blog.index')->with('success', __('Post created.'));
    }

    public function edit(BlogPost $post): Response
    {
        $post->load(['category', 'media']);

        return Inertia::render('admin/blog/edit', [
            'post' => new BlogPostResource($post),
            'categories' => BlogCategoryResource::collection($this->categories()),
        ]);
    }

    public function update(StoreBlogPostRequest $request, BlogPost $post, SavePost $action): RedirectResponse
    {
        $action->handle($request->validated(), null, $this->resolveAsset($request), $post);

        return to_route('admin.blog.index')->with('success', __('Post updated.'));
    }

    public function destroy(BlogPost $post, DeletePost $action): RedirectResponse
    {
        $action->handle($post);

        return to_route('admin.blog.index')->with('success', __('Post deleted.'));
    }

    /**
     * @return Collection<int, BlogCategory>
     */
    private function categories(): Collection
    {
        return BlogCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }
}
