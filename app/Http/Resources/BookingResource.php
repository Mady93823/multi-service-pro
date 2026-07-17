<?php

namespace App\Http\Resources;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(SettingsRegistry::class)->string('localization.timezone', 'Asia/Kolkata');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'scheduled_label' => $this->scheduled_at->timezone($timezone)->format('D, j M Y'),
            'slot_label' => $this->scheduled_at->timezone($timezone)->format('g:i A')
                .' – '.$this->slot_end_at->timezone($timezone)->format('g:i A'),
            'address' => $this->address_snapshot,
            // Snapshot on the booking; bookings from before the column existed
            // fall back to the customer's profile phone when it is loaded.
            'contact_phone' => $this->contact_phone
                ?? ($this->relationLoaded('customer') ? $this->customer?->phone : null),
            'contact_phone_alt' => $this->contact_phone_alt,
            'zone' => $this->whenLoaded('zone', fn () => $this->zone?->name),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider === null ? null : [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
            ]),
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
            'subtotal' => $this->subtotal,
            'addon_total' => $this->addon_total,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'tax_breakup' => $this->tax_breakup,
            'total' => $this->total,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method->value,
            'cancellation_fee' => $this->cancellation_fee,
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // The job-start code is for the customer to hand to the provider
            // on the doorstep — never expose it to the provider's own account.
            'job_otp_code' => $this->when(
                $request->user()?->id === $this->customer_id && $this->showOtpFor($this->status),
                $this->job_otp_code,
            ),
            'history' => BookingStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'photo_urls' => $this->whenLoaded('media', fn () => $this->getMedia('problem_photos')
                ->map(fn (Media $media): string => route('bookings.photos.show', [$this->resource, $media]))
                ->all()),
        ];
    }

    private function showOtpFor(BookingStatus $status): bool
    {
        return ! $status->isTerminal() && $status !== BookingStatus::InProgress;
    }
}
