<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\FooterPages;
use App\Models\Page;

class DeletePage
{
    public function __construct(private FooterPages $footerPages) {}

    public function handle(Page $page): void
    {
        $page->delete();

        $this->footerPages->flush();
    }
}
