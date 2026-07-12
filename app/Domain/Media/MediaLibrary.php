<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Read model for the media manager (M18).
 *
 * Usage comes from the `library_asset_id` custom property stamped on every copy
 * a consumer takes (D29) — no join table to keep in step. The grouping happens
 * in PHP rather than in a JSON-path aggregate, so MySQL, MariaDB and SQLite all
 * behave the same (the D12 rule: portable beats clever).
 */
class MediaLibrary
{
    /**
     * @return LengthAwarePaginator<int, MediaAsset>
     */
    public function assets(string $search = '', int $perPage = 24): LengthAwarePaginator
    {
        return MediaAsset::query()
            ->with(['media', 'uploader'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * asset id => number of consumer copies.
     *
     * @return array<int, int>
     */
    public function usageCounts(): array
    {
        return $this->copies()
            ->countBy(fn (int $assetId): int => $assetId)
            ->all();
    }

    /**
     * The wire shape of an asset. One mapper for the manager screen and for the
     * picker's JSON endpoint — two shapes would drift the moment one changes.
     *
     * @param  array<int, int>  $usage  from usageCounts()
     * @return array{id: int, name: string, url: string|null, thumb_url: string|null, size: int, uploaded_by: string|null, uploaded_at: string|null, usage_count: int}
     */
    public function toArray(MediaAsset $asset, array $usage = []): array
    {
        $file = $asset->file();

        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'url' => $file?->getUrl(),
            'thumb_url' => $file === null
                ? null
                : ($file->hasGeneratedConversion('thumb') ? $file->getUrl('thumb') : $file->getUrl()),
            'size' => (int) ($file->size ?? 0),
            'uploaded_by' => $asset->uploader?->name,
            'uploaded_at' => $asset->created_at?->format('j M Y'),
            'usage_count' => $usage[$asset->id] ?? 0,
        ];
    }

    /**
     * Library footprint. Only the library's own originals — a consumer's copy is
     * counted against the consumer, not the library.
     *
     * @return array{files: int, bytes: int, in_use: int}
     */
    public function stats(): array
    {
        $files = Media::query()->where('collection_name', MediaAsset::COLLECTION);

        return [
            'files' => (clone $files)->count(),
            'bytes' => (int) (clone $files)->sum('size'),
            'in_use' => $this->copies()->unique()->count(),
        ];
    }

    /**
     * Every consumer copy, as the library asset id it came from.
     *
     * @return Collection<int, int>
     */
    private function copies(): Collection
    {
        $ids = [];

        $copies = Media::query()
            ->whereNot('collection_name', MediaAsset::COLLECTION)
            ->get(['collection_name', 'custom_properties']);

        foreach ($copies as $media) {
            $assetId = $media->getCustomProperty(MediaAsset::USAGE_PROPERTY);

            if ($assetId !== null) {
                $ids[] = (int) $assetId;
            }
        }

        return collect($ids);
    }
}
