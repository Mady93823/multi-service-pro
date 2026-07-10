<?php

namespace App\Domain\Banners\Actions;

use App\Models\Banner;
use Illuminate\Http\UploadedFile;

class CreateBanner
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?UploadedFile $image): Banner
    {
        $banner = Banner::query()->create($data);

        if ($image !== null) {
            $banner->addMedia($image)->toMediaCollection('image');
        }

        return $banner;
    }
}
