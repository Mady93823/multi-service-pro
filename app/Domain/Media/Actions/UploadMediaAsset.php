<?php

namespace App\Domain\Media\Actions;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The only way a file enters the library (M18). Every image a consumer uploads
 * routes through here — a banner uploaded on the banner form becomes a library
 * asset first, so the library is the real inventory and not a second silo.
 */
class UploadMediaAsset
{
    public function handle(?User $uploader, UploadedFile $file): MediaAsset
    {
        return DB::transaction(function () use ($uploader, $file): MediaAsset {
            $asset = MediaAsset::query()->create([
                'name' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader?->id,
            ]);

            $asset->addMedia($file)->toMediaCollection(MediaAsset::COLLECTION);

            return $asset->refresh();
        });
    }
}
