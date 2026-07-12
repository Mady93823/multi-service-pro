<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Banners\Actions\CreateBanner;
use App\Domain\Banners\Actions\DeleteBanner;
use App\Domain\Banners\Actions\UpdateBanner;
use App\Domain\Media\Actions\UploadMediaAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(): Response
    {
        $banners = Banner::query()
            ->with('media')
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/banners/index', [
            'banners' => BannerResource::collection($banners),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/banners/create');
    }

    public function store(StoreBannerRequest $request, CreateBanner $action): RedirectResponse
    {
        $action->handle(
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
        );

        return to_route('admin.banners.index')->with('success', __('Banner created.'));
    }

    public function edit(Banner $banner): Response
    {
        return Inertia::render('admin/banners/edit', [
            'banner' => new BannerResource($banner->load('media')),
        ]);
    }

    public function update(UpdateBannerRequest $request, Banner $banner, UpdateBanner $action): RedirectResponse
    {
        $action->handle(
            $banner,
            $request->safe()->except(['image', 'media_asset_id']),
            $this->resolveAsset($request),
        );

        return to_route('admin.banners.index')->with('success', __('Banner updated.'));
    }

    public function destroy(Banner $banner, DeleteBanner $action): RedirectResponse
    {
        $action->handle($banner);

        return to_route('admin.banners.index')->with('success', __('Banner deleted.'));
    }

    /**
     * A banner's picture comes from the library either way (M18, D29): a picked
     * asset is used as-is, and an uploaded file becomes a library asset first.
     */
    private function resolveAsset(StoreBannerRequest $request): ?MediaAsset
    {
        $file = $request->file('image');

        if ($file instanceof UploadedFile) {
            /** @var User $admin */
            $admin = $request->user();

            return app(UploadMediaAsset::class)->handle($admin, $file);
        }

        $assetId = $request->safe()->integer('media_asset_id');

        return $assetId > 0 ? MediaAsset::query()->find($assetId) : null;
    }
}
