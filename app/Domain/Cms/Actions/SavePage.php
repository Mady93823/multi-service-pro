<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Cms\FooterPages;
use App\Models\Page;
use Illuminate\Support\Str;

class SavePage
{
    public function __construct(private FooterPages $footerPages) {}

    /**
     * Create or update a CMS page. A blank slug is derived from the title
     * and de-duplicated with a numeric suffix so saving never 500s on the
     * unique index.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Page $page = null): Page
    {
        $slug = is_string($data['slug'] ?? null) && $data['slug'] !== ''
            ? Str::slug($data['slug'])
            : Str::slug((string) $data['title']);

        $data['slug'] = $this->uniqueSlug($slug !== '' ? $slug : 'page', $page);

        if ($page === null) {
            $page = Page::query()->create($data);
        } else {
            $page->update($data);
        }

        $this->footerPages->flush();

        return $page;
    }

    private function uniqueSlug(string $base, ?Page $ignore): string
    {
        $slug = $base;

        for ($i = 2; $this->slugTaken($slug, $ignore); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?Page $ignore): bool
    {
        return Page::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();
    }
}
