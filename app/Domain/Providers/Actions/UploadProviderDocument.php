<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Domain\Providers\Enums\ProviderDocumentType;
use App\Models\ProviderDocument;
use App\Models\ProviderProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadProviderDocument
{
    /**
     * Store a KYC document on the private disk. Re-uploading a type
     * replaces the previous file and resets it to pending review.
     */
    public function handle(ProviderProfile $profile, ProviderDocumentType $type, UploadedFile $file): ProviderDocument
    {
        $path = $file->store("provider-documents/{$profile->id}", 'local');

        if ($path === false) {
            throw new \RuntimeException('Could not store the uploaded document.');
        }

        return DB::transaction(function () use ($profile, $type, $path): ProviderDocument {
            /** @var ProviderDocument|null $existing */
            $existing = $profile->documents()->where('type', $type->value)->first();

            if ($existing !== null) {
                Storage::disk('local')->delete($existing->file_path);
                $existing->delete();
            }

            /** @var ProviderDocument $document */
            $document = $profile->documents()->create([
                'type' => $type,
                'file_path' => $path,
                'status' => ProviderDocumentStatus::Pending,
            ]);

            // Fresh evidence after a rejection puts the profile back in
            // the review queue.
            if ($profile->approval_status === ProviderApprovalStatus::Rejected) {
                $profile->approval_status = ProviderApprovalStatus::Pending;
                $profile->approval_note = null;
                $profile->save();
            }

            return $document;
        });
    }
}
