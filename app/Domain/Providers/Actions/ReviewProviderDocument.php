<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Models\ProviderDocument;
use App\Models\User;

class ReviewProviderDocument
{
    public function handle(ProviderDocument $document, ProviderDocumentStatus $status, User $reviewer, ?string $rejectReason = null): ProviderDocument
    {
        $document->update([
            'status' => $status,
            'reject_reason' => $status === ProviderDocumentStatus::Rejected ? $rejectReason : null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $document;
    }
}
