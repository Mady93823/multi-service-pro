<?php

namespace App\Domain\Banners\Actions;

use App\Models\Banner;
use Illuminate\Http\UploadedFile;

class UpdateBanner
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Banner $banner, array $data, ?UploadedFile $image): Banner
    {
        $banner->update($data);

        if ($image !== null) {
            // singleFile collection — the old image is replaced automatically.
            $banner->addMedia($image)->toMediaCollection('image');
        }

        return $banner;
    }
}
