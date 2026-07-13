<?php

namespace App\Http\Resources;

use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin SupportTicketMessage
 */
class SupportTicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_staff' => $this->is_staff,
            // A guest's contact-form message has no author row — fall back to the
            // name they typed on the ticket (M19), when the caller loaded it.
            'author_name' => $this->author->name
                ?? ($this->relationLoaded('ticket') ? $this->ticket->guest_name : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'attachments' => $this->getMedia('attachments')->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->file_name,
                'size' => $media->size,
                'url' => route('support.attachments.show', ['ticket' => $this->ticket_id, 'media' => $media->id]),
            ])->values()->all(),
        ];
    }
}
