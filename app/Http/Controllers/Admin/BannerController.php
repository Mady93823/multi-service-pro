<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Banners\Actions\CreateBanner;
use App\Domain\Banners\Actions\DeleteBanner;
use App\Domain\Banners\Actions\UpdateBanner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
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
        $image = $request->file('image');
        $action->handle(
            $request->safe()->except(['image']),
            $image instanceof UploadedFile ? $image : null,
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
        $image = $request->file('image');
        $action->handle(
            $banner,
            $request->safe()->except(['image']),
            $image instanceof UploadedFile ? $image : null,
        );

        return to_route('admin.banners.index')->with('success', __('Banner updated.'));
    }

    public function destroy(Banner $banner, DeleteBanner $action): RedirectResponse
    {
        $action->handle($banner);

        return to_route('admin.banners.index')->with('success', __('Banner deleted.'));
    }
}
