<?php

namespace App\Domain\Blocks\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SaveBlock
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * Create or update a block. The payload arrives already validated against
     * the block's own rules (the request composes them), so this only has to
     * decide where the block sits and which pictures it owns.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Page $page, array $data, ?PageBlock $block = null): PageBlock
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        $attributes = [
            'payload' => $payload,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];

        if ($block === null) {
            $block = $page->blocks()->create([
                ...$attributes,
                'type' => (string) $data['type'],
                'sort_order' => $this->nextPosition($page),
            ]);
        } else {
            // The type is immutable: a block's payload only means anything to the
            // block that validated it.
            $block->update($attributes);
        }

        $this->syncImages($block, $payload);

        return $block;
    }

    private function nextPosition(Page $page): int
    {
        return (int) $page->blocks()->max('sort_order') + 1;
    }

    /**
     * Picking a library asset copies the file into the block (D29). A picture
     * dropped from the payload takes its copy with it, so a page never keeps
     * files nothing on it points at.
     *
     * @param  array<string, mixed>  $payload
     */
    private function syncImages(PageBlock $block, array $payload): void
    {
        $wanted = $this->assetIds($payload);
        $kept = [];

        foreach ($block->getMedia(PageBlock::COLLECTION) as $media) {
            /** @var Media $media */
            $assetId = (int) ($media->getCustomProperty(MediaAsset::USAGE_PROPERTY) ?? 0);

            if (in_array($assetId, $wanted, true)) {
                $kept[] = $assetId;

                continue;
            }

            $media->delete();
        }

        foreach ($wanted as $assetId) {
            if (in_array($assetId, $kept, true)) {
                continue;
            }

            $asset = MediaAsset::query()->find($assetId);

            if ($asset instanceof MediaAsset) {
                $this->attach->handle($block, $asset, PageBlock::COLLECTION);
            }
        }

        $block->unsetRelation('media');
    }

    /**
     * Every `media_asset_id` in the payload, at any depth — a hero has one at
     * the top level, a gallery has one per row.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<int>
     */
    private function assetIds(array $payload): array
    {
        $ids = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $ids = [...$ids, ...$this->assetIds($value)];

                continue;
            }

            if ($key === 'media_asset_id' && is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
