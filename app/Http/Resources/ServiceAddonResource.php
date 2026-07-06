<?php

namespace App\Http\Resources;

use App\Models\ServiceAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceAddon
 */
class ServiceAddonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'is_active' => $this->is_active,
        ];
    }
}
