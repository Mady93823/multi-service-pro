<?php

namespace App\Http\Resources;

use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sponsor
 */
class SponsorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logo = $this->getFirstMedia('logo');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'logo_url' => $logo?->getUrl('thumb'),
        ];
    }
}
