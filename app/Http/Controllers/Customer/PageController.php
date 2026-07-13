<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Blocks\PageBlocks;
use App\Domain\Cms\MarkdownRenderer;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BlockPresenter;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Public CMS page under the reserved /p/ prefix — a prefix, not a
     * catch-all, so a page slug can never shadow a real route (ADR D20).
     *
     * A page carries **either** blocks (M20) **or** a markdown body (M14): if it
     * has blocks they are the page, otherwise the body is. One page, one source
     * of truth — never both stacked on top of each other.
     */
    public function show(Page $page, MarkdownRenderer $renderer, PageBlocks $pageBlocks, BlockPresenter $presenter): Response
    {
        abort_unless($page->is_published, 404);
        // The home page lives at `/`, not here.
        abort_if($page->isHome(), 404);

        $blocks = $presenter->collection($pageBlocks->for($page));

        return Inertia::render('cms/show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                // Sanitized by the renderer (html_input: strip) — the only
                // producer of the HTML the frontend injects.
                'html' => $blocks === [] ? $renderer->render($page->body) : null,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'blocks' => $blocks,
        ]);
    }
}
