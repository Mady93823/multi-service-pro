<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderProfile
 */
class ProviderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,
            'base_lat' => $this->base_lat === null ? null : (float) $this->base_lat,
            'base_lng' => $this->base_lng === null ? null : (float) $this->base_lng,
            'service_radius_km' => $this->service_radius_km,
            'working_hours' => $this->working_hours,
            'is_online' => $this->is_online,
            'approval_status' => $this->approval_status->value,
            'approval_note' => $this->approval_note,
            'is_complete' => $this->isComplete(),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'jobs_completed' => $this->jobs_completed,
            'categories' => $this->whenLoaded(
                'categories',
                fn () => $this->categories->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->all(),
            ),
            'documents' => ProviderDocumentResource::collection($this->whenLoaded('documents')),
            'blackouts' => ProviderBlackoutResource::collection($this->whenLoaded('blackouts')),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
        ];
    }
}
