<?php

namespace App\Domain\Blog\Actions;

use App\Models\BlogCategory;
use Illuminate\Support\Str;

class SaveBlogCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?BlogCategory $category = null): BlogCategory
    {
        $data['slug'] = $this->uniqueSlug(
            is_string($data['slug'] ?? null) && $data['slug'] !== ''
                ? Str::slug($data['slug'])
                : Str::slug((string) $data['name']),
            $category,
        );

        if ($category === null) {
            return BlogCategory::query()->create($data);
        }

        $category->update($data);

        return $category;
    }

    private function uniqueSlug(string $base, ?BlogCategory $ignore): string
    {
        $slug = $base !== '' ? $base : 'category';

        for ($i = 2; $this->taken($slug, $ignore); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function taken(string $slug, ?BlogCategory $ignore): bool
    {
        return BlogCategory::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();
    }
}
