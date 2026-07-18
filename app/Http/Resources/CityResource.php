<?php

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'state' => $this->state,
            'timezone' => $this->timezone,
            'center_lat' => $this->center_lat,
            'center_lng' => $this->center_lng,
            'is_active' => $this->is_active,
            'cash_enabled' => $this->cash_enabled,
            'sort_order' => $this->sort_order,
            'zones_count' => $this->whenCounted('zones'),
        ];
    }
}
