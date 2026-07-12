<?php

namespace App\Domain\Banners\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\Banner;
use App\Models\MediaAsset;

class UpdateBanner
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Banner $banner, array $data, ?MediaAsset $asset): Banner
    {
        $banner->update($data);

        if ($asset !== null) {
            // singleFile collection — the old image is replaced automatically.
            $this->attach->handle($banner, $asset, 'image');
        }

        return $banner;
    }
}
