<?php

namespace App\Http\Resources;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $qr = $this->getFirstMedia('qr');

        return [
            'id' => $this->id,
            'label' => $this->label,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'ifsc' => $this->ifsc,
            'upi_id' => $this->upi_id,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'qr_url' => $qr?->getUrl(),
            'qr_thumb_url' => $qr === null ? null : $qr->getUrl('thumb'),
        ];
    }
}
