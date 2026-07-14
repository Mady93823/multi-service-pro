<?php

namespace App\Domain\Blocks;

use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A page's blocks, resolved for one visitor (M20).
 *
 * Resolution is total, the same way menus are (D30): a row whose type is not in
 * the registry, or whose block declines to render, is **dropped**. The renderer
 * therefore only ever meets blocks it knows, with payloads that were validated
 * on write.
 */
class PageBlocks
{
    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * @return list<array{id: int, type: string, data: array<string, mixed>}>
     */
    public function for(Page $page, ?int $zoneId = null, ?int $cityId = null): array
    {
        $blocks = $page->blocks()->live()->with('media')->get();

        return $this->render($blocks, $zoneId, $cityId);
    }

    /**
     * The storefront home page. An install whose home page has no blocks yet —
     * or an admin who deleted them all — still gets a usable storefront.
     *
     * @return list<array{id: int, type: string, data: array<string, mixed>}>
     */
    public function forHome(?int $zoneId = null, ?int $cityId = null): array
    {
        $home = Page::query()->where('slug', Page::HOME_SLUG)->first();

        $blocks = $home instanceof Page ? $this->for($home, $zoneId, $cityId) : [];

        return $blocks !== [] ? $blocks : $this->fallback($zoneId, $cityId);
    }

    /**
     * @param  Collection<int, PageBlock>  $blocks
     * @return list<array{id: int, type: string, data: array<string, mixed>}>
     */
    private function render(Collection $blocks, ?int $zoneId, ?int $cityId): array
    {
        $rendered = [];

        foreach ($blocks as $model) {
            $block = $this->registry->find($model->type);

            if ($block === null) {
                continue;
            }

            $data = $block->data($model->payload, new BlockContext($zoneId, $cityId, $this->images($model)));

            if ($data === null) {
                continue;
            }

            $rendered[] = ['id' => $model->id, 'type' => $model->type, 'data' => $data];
        }

        return $rendered;
    }

    /**
     * The block's own picture copies, keyed by the library asset its payload
     * names (D29).
     *
     * @return array<int, array{url: string, thumb_url: string, card_url: string}>
     */
    private function images(PageBlock $model): array
    {
        $images = [];

        foreach ($model->getMedia(PageBlock::COLLECTION) as $media) {
            /** @var Media $media */
            $assetId = (int) ($media->getCustomProperty(MediaAsset::USAGE_PROPERTY) ?? 0);

            if ($assetId === 0) {
                continue;
            }

            $images[$assetId] = [
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'card_url' => $media->getUrl('card'),
            ];
        }

        return $images;
    }

    /**
     * @return list<array{id: int, type: string, data: array<string, mixed>}>
     */
    private function fallback(?int $zoneId, ?int $cityId): array
    {
        $rendered = [];
        $id = -1;

        foreach (['categories_grid', 'services_grid'] as $type) {
            $block = $this->registry->find($type);

            if ($block === null) {
                continue;
            }

            $data = $block->data($block->defaults(), new BlockContext($zoneId, $cityId));

            if ($data === null) {
                continue;
            }

            $rendered[] = ['id' => $id--, 'type' => $type, 'data' => $data];
        }

        return $rendered;
    }
}
