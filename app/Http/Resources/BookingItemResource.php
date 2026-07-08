<?php

namespace App\Http\Resources;

use App\Models\BookingItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingItem
 */
class BookingItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->name_snapshot,
            'price' => $this->price_snapshot,
            'qty' => $this->qty,
            'addons' => $this->addons_snapshot ?? [],
            'line_total' => $this->lineTotal(),
        ];
    }
}
