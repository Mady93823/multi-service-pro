<?php

namespace App\Http\Resources;

use App\Models\ProviderDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderDocument
 */
class ProviderDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'reject_reason' => $this->reject_reason,
            'is_pdf' => str_ends_with(strtolower($this->file_path), '.pdf'),
            'url' => route('provider-documents.show', $this->resource),
            'uploaded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
