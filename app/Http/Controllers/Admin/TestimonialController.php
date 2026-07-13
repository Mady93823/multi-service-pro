<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Actions\SaveTestimonial;
use App\Http\Controllers\Concerns\ResolvesMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    use ResolvesMediaAsset;

    public function index(): Response
    {
        return Inertia::render('admin/testimonials/index', [
            'testimonials' => TestimonialResource::collection(
                Testimonial::query()->with('media')->orderBy('sort_order')->orderBy('id')->get(),
            ),
        ]);
    }

    public function store(StoreTestimonialRequest $request, SaveTestimonial $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
        );

        return back()->with('success', __('Testimonial added.'));
    }

    public function update(StoreTestimonialRequest $request, Testimonial $testimonial, SaveTestimonial $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
            $testimonial,
        );

        return back()->with('success', __('Testimonial updated.'));
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('success', __('Testimonial deleted.'));
    }
}
