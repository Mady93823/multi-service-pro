<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        $faqs = Faq::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/faqs/index', [
            // Resource collection on purpose — the shared <Pagination>
            // needs the data/meta/links shape (landmine: /admin/providers).
            'faqs' => FaqResource::collection($faqs),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/faqs/create');
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        Faq::query()->create($request->validated());

        return to_route('admin.faqs.index')->with('success', __('FAQ created.'));
    }

    public function edit(Faq $faq): Response
    {
        return Inertia::render('admin/faqs/edit', [
            'faq' => new FaqResource($faq),
        ]);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validated());

        return to_route('admin.faqs.index')->with('success', __('FAQ updated.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return to_route('admin.faqs.index')->with('success', __('FAQ deleted.'));
    }
}
