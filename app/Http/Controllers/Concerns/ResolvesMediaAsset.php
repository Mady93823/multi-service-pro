<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Media\Actions\UploadMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Every admin form that carries a picture offers the same two ways to give one:
 * pick from the media library, or upload. Both end in the library (M18, D29) —
 * an upload becomes a library asset first, so nothing an admin uploads is
 * invisible in the manager.
 */
trait ResolvesMediaAsset
{
    protected function resolveAsset(FormRequest $request, string $field = 'image'): ?MediaAsset
    {
        $file = $request->file($field);

        if ($file instanceof UploadedFile) {
            /** @var User $admin */
            $admin = $request->user();

            return app(UploadMediaAsset::class)->handle($admin, $file);
        }

        $assetId = $request->safe()->integer('media_asset_id');

        return $assetId > 0 ? MediaAsset::query()->find($assetId) : null;
    }
}
