<?php

namespace App\Domain\Blog\Actions;

use App\Models\BlogCategory;

class DeleteBlogCategory
{
    /**
     * Deleting a category never deletes posts — the FK is nullOnDelete, so the
     * posts survive as uncategorised.
     */
    public function handle(BlogCategory $category): void
    {
        $category->delete();
    }
}
