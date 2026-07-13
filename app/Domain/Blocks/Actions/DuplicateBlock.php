<?php

namespace App\Domain\Blocks\Actions;

use App\Models\PageBlock;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DuplicateBlock
{
    /**
     * Copy a block to the end of its page — pictures included. The copies carry
     * the same `library_asset_id` stamp, so the media manager still counts both
     * uses and still refuses to delete the asset (D29).
     */
    public function handle(PageBlock $block): PageBlock
    {
        $last = PageBlock::query()->where('page_id', $block->page_id)->max('sort_order');

        $copy = $block->replicate();
        $copy->sort_order = (int) $last + 1;
        $copy->save();

        foreach ($block->getMedia(PageBlock::COLLECTION) as $media) {
            /** @var Media $media */
            $copy
                ->addMedia($media->getPath())
                ->preservingOriginal()
                ->usingFileName($media->file_name)
                ->usingName($media->name)
                ->withCustomProperties($media->custom_properties)
                ->toMediaCollection(PageBlock::COLLECTION);
        }

        return $copy;
    }
}
