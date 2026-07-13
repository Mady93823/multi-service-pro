<?php

namespace App\Domain\Blog\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Str;

class SavePost
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $author = null, ?MediaAsset $cover = null, ?BlogPost $post = null): BlogPost
    {
        $data['slug'] = $this->uniqueSlug(
            is_string($data['slug'] ?? null) && $data['slug'] !== ''
                ? Str::slug($data['slug'])
                : Str::slug((string) $data['title']),
            $post,
        );

        $published = (bool) ($data['is_published'] ?? false);

        // Publishing without a date means "now"; a date in the future means the
        // post is scheduled, and scopePublished keeps it invisible until then.
        if ($published && ($data['published_at'] ?? null) === null) {
            $data['published_at'] = now();
        }

        if (! $published) {
            $data['published_at'] = null;
        }

        if ($post === null) {
            $data['author_id'] = $author?->id;
            $post = BlogPost::query()->create($data);
        } else {
            $post->update($data);
        }

        if ($cover !== null) {
            $this->attach->handle($post, $cover, BlogPost::COLLECTION);
        }

        return $post;
    }

    private function uniqueSlug(string $base, ?BlogPost $ignore): string
    {
        $slug = $base !== '' ? $base : 'post';

        for ($i = 2; $this->taken($slug, $ignore); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function taken(string $slug, ?BlogPost $ignore): bool
    {
        return BlogPost::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();
    }
}
