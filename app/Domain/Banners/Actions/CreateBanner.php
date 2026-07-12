<?php

namespace App\Domain\Banners\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\Banner;
use App\Models\MediaAsset;

class CreateBanner
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * The picture always comes from the media library (M18, D29) — the banner
     * form's own upload creates the library asset first, so nothing an admin
     * uploads is invisible in the manager.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MediaAsset $asset): Banner
    {
        $banner = Banner::query()->create($data);

        if ($asset !== null) {
            $this->attach->handle($banner, $asset, 'image');
        }

        return $banner;
    }
}
