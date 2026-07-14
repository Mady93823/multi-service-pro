<?php

namespace App\Http\Resources;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city_id' => $this->city_id,
            'city_name' => $this->whenLoaded('city', fn (): string => $this->city->name),
            'geojson' => $this->geojson,
            'is_active' => $this->is_active,
            'services_count' => $this->whenCounted('services'),
            'addresses_count' => $this->whenCounted('addresses'),
        ];
    }
}
