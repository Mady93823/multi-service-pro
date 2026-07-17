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
     * @param  array{parent_id?: int|null, name: string, type?: string|null, sort_order?: int, is_active?: bool, commission_percent?: string|null}  $data
     */
    public function handle(Category $category, array $data, ?UploadedFile $icon = null, ?UploadedFile $image = null): Category
    {
        // A child always lives on its parent's surface; moving a root between
        // surfaces carries its children with it — one tree, one page.
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $data['type'] = Category::query()->whereKey($parentId)->value('type');
        } elseif (($data['type'] ?? null) === null) {
            unset($data['type']);
        }

        if ($icon !== null) {
            $this->deleteFile($category->icon_path);
            $data['icon_path'] = $icon->store('categories/icons', 'public');
        }

        if ($image !== null) {
            $this->deleteFile($category->image_path);
            $data['image_path'] = $image->store('categories/images', 'public');
        }

        $category->update($data);

        if ($category->parent_id === null) {
            $category->children()->update(['type' => $category->type->value]);
        }

        return $category->refresh();
    }

    protected function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
