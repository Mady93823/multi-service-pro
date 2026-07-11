<?php

namespace App\Http\Resources;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'subject' => $this->subject,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'resolution_note' => $this->resolution_note,
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'messages_count' => $this->whenCounted('messages'),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'booking' => $this->whenLoaded('booking', fn (): ?array => $this->booking === null ? null : [
                'id' => $this->booking->id,
                'code' => $this->booking->code,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
        ];
    }
}
