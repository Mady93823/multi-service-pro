<?php

namespace App\Http\Resources;

use App\Domain\Settings\SettingsRegistry;
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
            // An open offer is an invitation, not a job (D41): the provider
            // gets what the decision needs — what, when, how much, which part
            // of town — and never the doorstep. No street line, no pin, no
            // phone: Inertia serializes every prop into the page HTML, so "the
            // card only shows the city" is presentation, not privacy.
            'booking' => $this->whenLoaded('booking', function (): array {
                $timezone = app(SettingsRegistry::class)->string('localization.timezone', 'Asia/Kolkata');

                return [
                    'id' => $this->booking->id,
                    'status' => $this->booking->status->value,
                    'scheduled_label' => $this->booking->scheduled_at->timezone($timezone)->format('D, j M Y'),
                    'slot_label' => $this->booking->scheduled_at->timezone($timezone)->format('g:i A')
                        .' – '.$this->booking->slot_end_at->timezone($timezone)->format('g:i A'),
                    'total' => $this->booking->total,
                    'items' => BookingItemResource::collection($this->booking->items),
                    'address' => ['city' => (string) ($this->booking->address_snapshot['city'] ?? '')],
                ];
            }),
        ];
    }
}
