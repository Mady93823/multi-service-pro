<?php

namespace App\Domain\Banners\Actions;

use App\Models\Banner;

class DeleteBanner
{
    public function handle(Banner $banner): void
    {
        $banner->delete(); // medialibrary removes the files with the model
    }
}
