<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cms\Actions\DeletePage;
use App\Domain\Cms\Actions\SavePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(15);

        return Inertia::render('admin/pages/index', [
            // Resource collection on purpose — the shared <Pagination>
            // needs the data/meta/links shape (landmine: /admin/providers).
            'pages' => PageResource::collection($pages),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/create');
    }

    public function store(StorePageRequest $request, SavePage $action): RedirectResponse
    {
        $action->handle($request->validated());

        return to_route('admin.pages.index')->with('success', __('Page created.'));
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/edit', [
            'page' => new PageResource($page),
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page, SavePage $action): RedirectResponse
    {
        $action->handle($request->validated(), $page);

        return to_route('admin.pages.index')->with('success', __('Page updated.'));
    }

    public function destroy(Page $page, DeletePage $action): RedirectResponse
    {
        $action->handle($page);

        return to_route('admin.pages.index')->with('success', __('Page deleted.'));
    }
}
