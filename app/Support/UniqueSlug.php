<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UniqueSlug
{
    /**
     * Generate a slug unique within the given query, suffixing -2, -3, ...
     * Pass a withTrashed() query for soft-deleting models — the slug column
     * stays unique at the DB level even for trashed rows.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function for(Builder $query, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            (clone $query)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn (Builder $inner) => $inner->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
