<?php

namespace App\Domain\Media\Actions;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Picking a library asset **copies** its file into the consumer's own
 * collection (ADR D29) — consumers never share a medialibrary row.
 *
 * Why copy: a medialibrary row belongs to exactly one model. Sharing one would
 * mean deleting the library asset silently blanks every banner and page that
 * used it. The copy is tagged with `library_asset_id`, which is what makes
 * "used in 3 places" answerable without a join table.
 */
class AttachLibraryAsset
{
    public function handle(HasMedia&Model $model, MediaAsset $asset, string $collection): Media
    {
        $source = $asset->file();

        if ($source === null) {
            throw ValidationException::withMessages([
                'media_asset_id' => __('That library asset has no file.'),
            ]);
        }

        return $model
            ->addMedia($source->getPath())
            // Without this the source file is MOVED out of the library.
            ->preservingOriginal()
            ->usingFileName($source->file_name)
            ->usingName($source->name)
            ->withCustomProperties([MediaAsset::USAGE_PROPERTY => $asset->id])
            ->toMediaCollection($collection);
    }
}
