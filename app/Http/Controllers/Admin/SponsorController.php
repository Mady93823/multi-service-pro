<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Actions\SaveSponsor;
use App\Http\Controllers\Concerns\ResolvesMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSponsorRequest;
use App\Http\Resources\SponsorResource;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SponsorController extends Controller
{
    use ResolvesMediaAsset;

    public function index(): Response
    {
        return Inertia::render('admin/sponsors/index', [
            'sponsors' => SponsorResource::collection(
                Sponsor::query()->with('media')->orderBy('sort_order')->orderBy('id')->get(),
            ),
        ]);
    }

    public function store(StoreSponsorRequest $request, SaveSponsor $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
        );

        return back()->with('success', __('Sponsor added.'));
    }

    public function update(StoreSponsorRequest $request, Sponsor $sponsor, SaveSponsor $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
            $sponsor,
        );

        return back()->with('success', __('Sponsor updated.'));
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $sponsor->delete();

        return back()->with('success', __('Sponsor deleted.'));
    }
}
