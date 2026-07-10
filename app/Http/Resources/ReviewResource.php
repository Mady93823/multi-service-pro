<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            // Hidden state ships only where the query already allowed it:
            // storefront queries are scoped visible(), so these never leak
            // a moderation note to the public.
            'is_hidden' => $this->is_hidden,
            'hidden_reason' => $this->hidden_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'customer_name' => $this->whenLoaded('customer', fn (): string => $this->customer->name),
            'provider_name' => $this->whenLoaded('provider', fn (): string => $this->provider->name),
            'booking_code' => $this->whenLoaded('booking', fn (): string => $this->booking->code),
            'booking_id' => $this->booking_id,
            'photo_urls' => $this->whenLoaded('media', fn () => $this->getMedia('review_photos')
                ->map(fn (Media $media): string => route('reviews.photos.show', [$this->resource, $media]))
                ->all()),
        ];
    }
}
