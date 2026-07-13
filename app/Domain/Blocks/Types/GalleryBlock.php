<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class GalleryBlock extends Block
{
    public function type(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return __('Gallery');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading')),
            BlockField::repeater('items', __('Pictures'), [
                BlockField::media(__('Picture')),
                BlockField::text('caption', __('Caption')),
            ]),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'items' => ['array', 'max:24'],
            'items.*.media_asset_id' => ['required', 'integer', 'exists:media_assets,id'],
            'items.*.caption' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        $items = [];

        foreach ($this->rows($payload, 'items') as $row) {
            $image = $context->image($row['media_asset_id'] ?? null);

            // A picture whose copy is gone drops out of the gallery rather than
            // rendering a broken <img>.
            if ($image === null) {
                continue;
            }

            $items[] = [
                'url' => $image['url'],
                'thumb_url' => $image['thumb_url'],
                'caption' => $this->nullableText($row, 'caption'),
            ];
        }

        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'items' => $items,
        ];
    }
}
