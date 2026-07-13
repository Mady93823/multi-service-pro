<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\FooterPages;
use App\Models\Page;
use Illuminate\Validation\ValidationException;

class DeletePage
{
    public function __construct(private FooterPages $footerPages) {}

    public function handle(Page $page): void
    {
        // The home page *is* the storefront's front page (M20) — deleting it
        // would leave the site's front door with nothing behind it.
        if ($page->isHome()) {
            throw ValidationException::withMessages([
                'page' => __('The home page cannot be deleted. Edit its blocks instead.'),
            ]);
        }

        $page->delete();

        $this->footerPages->flush();
    }
}
