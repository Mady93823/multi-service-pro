<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'icon_url' => $this->icon_path !== null ? Storage::disk('public')->url($this->icon_path) : null,
            'image_url' => $this->image_path !== null ? Storage::disk('public')->url($this->image_path) : null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'commission_percent' => $this->commission_percent,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'services_count' => $this->whenCounted('services'),
        ];
    }
}
