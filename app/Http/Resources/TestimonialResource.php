<?php

namespace App\Http\Resources;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatar = $this->getFirstMedia('avatar');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'quote' => $this->quote,
            'rating' => $this->rating,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'from_review' => $this->review_id !== null,
            'avatar_url' => $avatar?->getUrl('thumb'),
        ];
    }
}
