<?php

namespace App\Http\Resources;

use App\Domain\Settings\SettingsRegistry;
use App\Models\BookingStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingStatusHistory
 */
class BookingStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(SettingsRegistry::class)->string('localization.timezone', 'Asia/Kolkata');

        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'actor_type' => $this->actor_type,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
            'created_label' => $this->created_at->timezone($timezone)->format('j M Y, g:i A'),
        ];
    }
}
