<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogPostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            // Blank slug = derive from the title; SavePost de-duplicates.
            'slug' => ['nullable', 'string', 'alpha_dash:ascii', 'max:150'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:65535'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'is_featured' => ['boolean'],
            'is_published' => ['boolean'],
            // A date in the future is a *scheduled* post, not an error.
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:150'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
