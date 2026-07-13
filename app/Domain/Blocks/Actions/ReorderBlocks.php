<?php

namespace App\Domain\Blocks\Actions;

use App\Models\Page;
use App\Models\PageBlock;

class ReorderBlocks
{
    /**
     * @param  list<int>  $ids  block ids in their new order
     */
    public function handle(Page $page, array $ids): void
    {
        // Scoped to the page: an id from another page in the payload is ignored,
        // not stolen (same rule as the menu reorder).
        $blocks = $page->blocks()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $position => $id) {
            $block = $blocks->get($id);

            if ($block instanceof PageBlock) {
                $block->update(['sort_order' => $position + 1]);
            }
        }
    }
}
