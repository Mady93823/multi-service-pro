<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => (string) $this->value,
            'max_discount' => $this->max_discount !== null ? (string) $this->max_discount : null,
            'min_order' => $this->min_order !== null ? (string) $this->min_order : null,
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'first_order_only' => $this->first_order_only,
            'starts_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'is_active' => $this->is_active,
            'usages_count' => $this->whenCounted('usages'),
            'created_at' => $this->created_at?->format('j M Y'),
        ];
    }
}
