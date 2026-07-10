<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateCategory
{
    /**
     * Slug intentionally stays stable on rename — public URLs keep working.
     *
     * @param  array{parent_id?: int|null, name: string, sort_order?: int, is_active?: bool, commission_percent?: string|null}  $data
     */
    public function handle(Category $category, array $data, ?UploadedFile $icon = null, ?UploadedFile $image = null): Category
    {
        if ($icon !== null) {
            $this->deleteFile($category->icon_path);
            $data['icon_path'] = $icon->store('categories/icons', 'public');
        }

        if ($image !== null) {
            $this->deleteFile($category->image_path);
            $data['image_path'] = $image->store('categories/images', 'public');
        }

        $category->update($data);

        return $category->refresh();
    }

    protected function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
