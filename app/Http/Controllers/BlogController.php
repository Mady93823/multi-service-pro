<?php

namespace App\Http\Controllers;

use App\Domain\Cms\MarkdownRenderer;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Resources\BlogCategoryResource;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public blog (M21). Every entry point goes through `enabled()` — a buyer
 * who does not want a blog switches it off and the URLs stop existing, rather
 * than serving an empty page that search engines then index.
 */
class BlogController extends Controller
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function index(Request $request): Response
    {
        $this->enabled();

        $search = (string) $request->string('search');
        $categorySlug = (string) $request->string('category');

        $posts = BlogPost::query()
            ->published()
            ->search($search)
            ->when($categorySlug !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $inner) => $inner->where('slug', $categorySlug),
            ))
            ->with(['category', 'author', 'media'])
            ->orderByDesc('published_at')
            ->paginate($this->settings->integer('blog.posts_per_page', 9))
            ->withQueryString();

        return Inertia::render('blog/index', [
            'posts' => BlogPostResource::collection($posts),
            'categories' => BlogCategoryResource::collection(
                BlogCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            ),
            'featured' => BlogPostResource::collection(
                BlogPost::query()
                    ->published()
                    ->where('is_featured', true)
                    ->with(['category', 'author', 'media'])
                    ->orderByDesc('published_at')
                    ->limit(1)
                    ->get(),
            ),
            'search' => $search,
            'category' => $categorySlug,
            'show_author' => $this->settings->boolean('blog.show_author', true),
        ]);
    }

    public function show(BlogPost $post, MarkdownRenderer $renderer): Response
    {
        $this->enabled();
        // A draft — or a post whose publication moment has not arrived — does
        // not exist to the public. Not "empty": absent.
        abort_unless(BlogPost::query()->published()->whereKey($post->id)->exists(), 404);

        $post->load(['category', 'author', 'media']);

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->when(
                $post->blog_category_id !== null,
                fn (Builder $query) => $query->where('blog_category_id', $post->blog_category_id),
            )
            ->with(['category', 'media'])
            ->orderByDesc('published_at')
            ->limit($this->settings->integer('blog.related_count', 3))
            ->get();

        return Inertia::render('blog/show', [
            'post' => new BlogPostResource($post),
            // Sanitized by the one renderer (D20) — raw HTML in the source is
            // stripped, so an admin cannot smuggle script onto a public page.
            'html' => $renderer->render($post->body),
            'related' => BlogPostResource::collection($related),
            'show_author' => $this->settings->boolean('blog.show_author', true),
            // Consumed by the <Head> tags today; M24's SEO layer reuses them.
            'meta' => [
                'title' => $post->meta_title ?? $post->title,
                'description' => $post->meta_description ?? $post->excerpt,
                'image' => $post->coverUrl('hero'),
                'url' => route('blog.show', $post->slug),
            ],
        ]);
    }

    /**
     * RSS. A feed is XML, not an Inertia page — it is rendered by the first
     * Blade view since the invoice (M09).
     */
    public function feed(): HttpResponse
    {
        $this->enabled();

        $posts = BlogPost::query()
            ->published()
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('feeds.blog', [
                'title' => $this->settings->string('branding.app_name', (string) config('app.name')),
                'posts' => $posts,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    private function enabled(): void
    {
        abort_unless($this->settings->boolean('blog.enabled', true), 404);
    }
}
