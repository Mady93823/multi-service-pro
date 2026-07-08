<?php

namespace App\Http\Resources;

use App\Models\DispatchOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DispatchOffer
 */
class DispatchOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'strategy' => $this->strategy->value,
            'round' => $this->round,
            'distance_km' => $this->distance_km,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider === null ? null : [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
            ]),
            'booking' => new BookingResource($this->whenLoaded('booking')),
        ];
    }
}
