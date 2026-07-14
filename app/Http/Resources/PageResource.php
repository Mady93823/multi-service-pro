<?php

namespace App\Http\Resources;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            // M24: null means the site-wide SEO defaults apply.
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_published' => $this->is_published,
            'show_in_footer' => $this->show_in_footer,
            'sort_order' => $this->sort_order,
            // M20: the home page is a page like any other — except it cannot be
            // deleted and it renders at `/`, not at /p/home.
            'is_home' => $this->resource->isHome(),
            'blocks_count' => $this->whenCounted('blocks'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
