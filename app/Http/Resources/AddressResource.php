<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'is_default' => $this->is_default,
            'zone' => $this->whenLoaded('zone', fn () => new ZoneResource($this->zone)),
        ];
    }
}
