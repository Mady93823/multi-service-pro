<?php

namespace App\Http\Resources;

use App\Models\ProviderBlackout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderBlackout
 */
class ProviderBlackoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'starts_label' => $this->starts_on->format('j M Y'),
            'ends_label' => $this->ends_on->format('j M Y'),
            'reason' => $this->reason,
        ];
    }
}
