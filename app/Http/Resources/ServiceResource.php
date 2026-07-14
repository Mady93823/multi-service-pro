<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            // M24: null means the site-wide SEO defaults apply.
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'pricing_type' => $this->pricing_type,
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'image_thumb_url' => $this->imageUrl('thumb'),
            'image_card_url' => $this->imageUrl('card'),
            'image_hero_url' => $this->imageUrl('hero'),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'addons' => ServiceAddonResource::collection($this->whenLoaded('addons')),
            'related' => ServiceResource::collection($this->whenLoaded('related')),
            'addons_count' => $this->whenCounted('addons'),
            'zone_ids' => $this->whenLoaded('zones', fn () => $this->zones->pluck('id')->all()),
        ];
    }

    protected function imageUrl(string $conversion): ?string
    {
        $url = $this->resource->getFirstMediaUrl('images', $conversion);

        return $url === '' ? null : $url;
    }
}
