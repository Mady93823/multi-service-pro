<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Category;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;

class CreateCategory
{
    /**
     * @param  array{parent_id?: int|null, name: string, sort_order?: int, is_active?: bool, commission_percent?: string|null}  $data
     */
    public function handle(array $data, ?UploadedFile $icon = null, ?UploadedFile $image = null): Category
    {
        $data['slug'] = UniqueSlug::for(Category::withTrashed(), $data['name']);

        if ($icon !== null) {
            $data['icon_path'] = $icon->store('categories/icons', 'public');
        }

        if ($image !== null) {
            $data['image_path'] = $image->store('categories/images', 'public');
        }

        return Category::create($data);
    }
}
