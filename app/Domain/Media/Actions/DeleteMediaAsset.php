<?php

namespace App\Domain\Media\Actions;

use App\Models\MediaAsset;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a library asset removes the original only. Copies a consumer already
 * took keep working (D29) — but an asset still in use is refused anyway, because
 * "delete" that leaves the picture on the homepage is a lie the admin will only
 * discover later.
 */
class DeleteMediaAsset
{
    public function handle(MediaAsset $asset, bool $force = false): void
    {
        if (! $force && $asset->usageCount() > 0) {
            throw ValidationException::withMessages([
                'asset' => __('This file is in use. Remove it where it is used first.'),
            ]);
        }

        // Media rows cascade with the model; the file goes with them.
        $asset->delete();
    }
}
