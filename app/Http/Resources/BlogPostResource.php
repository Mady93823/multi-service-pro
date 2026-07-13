<?php

namespace App\Http\Resources;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
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
            'excerpt' => $this->excerpt,
            // The markdown source is admin-only; the public screens receive the
            // rendered HTML from the controller instead (D20).
            'body' => $this->when($request->is('admin/*'), fn (): string => $this->body),
            'tags' => $this->tags ?? [],
            'is_featured' => $this->is_featured,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'cover_url' => $this->coverUrl('card'),
            'cover_hero_url' => $this->coverUrl('hero'),
            'category' => new BlogCategoryResource($this->whenLoaded('category')),
            'author' => $this->when($this->relationLoaded('author'), fn (): ?string => $this->author?->name),
        ];
    }
}
