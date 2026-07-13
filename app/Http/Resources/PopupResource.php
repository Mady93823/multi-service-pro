<?php

namespace App\Http\Resources;

use App\Models\Popup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Popup
 */
class PopupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->getFirstMedia('image');

        return [
            'id' => $this->id,
            'title' => $this->title,
            // The admin edits markdown source; only the storefront gets HTML.
            'body' => $this->body,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'audience' => $this->audience->value,
            'audience_label' => $this->audience->label(),
            'frequency_days' => $this->frequency_days,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'is_active' => $this->is_active,
            'image_url' => $image?->getUrl('card'),
        ];
    }
}
