<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Cms\MarkdownRenderer;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Public CMS page under the reserved /p/{slug} prefix — a prefix, not a
     * catch-all, so a page slug can never shadow a real route (ADR D20).
     */
    public function show(Page $page, MarkdownRenderer $renderer): Response
    {
        abort_unless($page->is_published, 404);

        return Inertia::render('cms/show', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                // Sanitized by the renderer (html_input: strip) — the only
                // producer of the HTML the frontend injects.
                'html' => $renderer->render($page->body),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
