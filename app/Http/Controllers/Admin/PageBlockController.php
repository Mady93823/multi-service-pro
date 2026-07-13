<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Blocks\Actions\DeleteBlock;
use App\Domain\Blocks\Actions\DuplicateBlock;
use App\Domain\Blocks\Actions\ReorderBlocks;
use App\Domain\Blocks\Actions\SaveBlock;
use App\Domain\Blocks\BlockRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderPageBlocksRequest;
use App\Http\Requests\Admin\SavePageBlockRequest;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PageBlockController extends Controller
{
    public function index(Page $page, BlockRegistry $registry): Response
    {
        $page->load(['blocks.media']);

        return Inertia::render('admin/pages/blocks', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'is_home' => $page->isHome(),
                'is_published' => $page->is_published,
            ],
            'blocks' => $page->blocks->map(fn (PageBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'label' => $registry->find($block->type)?->label(),
                'payload' => $block->payload,
                'is_active' => $block->is_active,
                'starts_at' => $block->starts_at?->toDateString(),
                'ends_at' => $block->ends_at?->toDateString(),
                // Thumbnails of the pictures this block already carries, keyed by
                // the library asset its payload names — the picker needs them to
                // show what is currently chosen.
                'image_urls' => $this->imageUrls($block),
            ])->all(),
            'schema' => $registry->schema(),
        ]);
    }

    public function store(SavePageBlockRequest $request, Page $page, SaveBlock $action): RedirectResponse
    {
        $action->handle($page, $request->validated());

        return back()->with('success', __('Block added.'));
    }

    public function update(SavePageBlockRequest $request, Page $page, PageBlock $block, SaveBlock $action): RedirectResponse
    {
        abort_unless($block->page_id === $page->id, 404);

        $action->handle($page, $request->validated(), $block);

        return back()->with('success', __('Block updated.'));
    }

    public function destroy(Page $page, PageBlock $block, DeleteBlock $action): RedirectResponse
    {
        abort_unless($block->page_id === $page->id, 404);

        $action->handle($block);

        return back()->with('success', __('Block deleted.'));
    }

    public function duplicate(Page $page, PageBlock $block, DuplicateBlock $action): RedirectResponse
    {
        abort_unless($block->page_id === $page->id, 404);

        $action->handle($block);

        return back()->with('success', __('Block duplicated.'));
    }

    public function reorder(ReorderPageBlocksRequest $request, Page $page, ReorderBlocks $action): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated()['ids'];

        $action->handle($page, $ids);

        return back();
    }

    /**
     * @return array<int, string>
     */
    private function imageUrls(PageBlock $block): array
    {
        $urls = [];

        foreach ($block->getMedia(PageBlock::COLLECTION) as $media) {
            /** @var Media $media */
            $assetId = (int) ($media->getCustomProperty(MediaAsset::USAGE_PROPERTY) ?? 0);

            if ($assetId > 0) {
                $urls[$assetId] = $media->getUrl('thumb');
            }
        }

        return $urls;
    }
}
