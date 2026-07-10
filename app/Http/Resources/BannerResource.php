<?php

namespace App\Http\Resources;

use App\Domain\Banners\Enums\BannerPlacement;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversion = $this->placement === BannerPlacement::HomeHero ? 'hero' : 'card';
        $imageUrl = $this->getFirstMediaUrl('image', $conversion);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'link_url' => $this->link_url,
            'placement' => $this->placement->value,
            'sort_order' => $this->sort_order,
            'starts_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'is_active' => $this->is_active,
            'image_url' => $imageUrl !== '' ? $imageUrl : ($this->getFirstMediaUrl('image') ?: null),
        ];
    }
}
