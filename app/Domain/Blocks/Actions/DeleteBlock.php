<?php

namespace App\Domain\Blocks\Actions;

use App\Models\PageBlock;

class DeleteBlock
{
    /**
     * Deleting a block deletes its picture copies with it — the library asset
     * they were copied from is untouched.
     */
    public function handle(PageBlock $block): void
    {
        $block->delete();
    }
}
