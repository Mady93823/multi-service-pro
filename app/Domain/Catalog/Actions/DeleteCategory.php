<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    /**
     * Soft-deletes. Blocked while sub-categories or services still point
     * here so the public catalog never shows orphans.
     *
     * @throws ValidationException
     */
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('Remove or move its sub-categories first.'),
            ]);
        }

        if ($category->services()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('Remove or move its services first.'),
            ]);
        }

        $category->delete();
    }
}
