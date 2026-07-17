<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A reusable image in the media library (M18).
 *
 * Lives on the **public** disk — the library is for marketing assets, never for
 * user uploads (KYC, booking photos, review photos, ticket attachments stay on
 * the private disk and are never listed by the manager).
 *
 * @property string $name
 */
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, InteractsWithMedia;

    public const COLLECTION = 'library';

    /** Stamped onto every copy a consumer takes, so usage is answerable. */
    public const USAGE_PROPERTY = 'library_asset_id';

    protected $fillable = [
        'name',
        'uploaded_by',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(400)->format('webp');
        $this->addMediaConversion('card')->nonQueued()->width(800)->format('webp');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    /**
     * How many consumer models copied this asset (D29). Zero = safe to delete.
     *
     * Filtered in PHP, not through a JSON-path WHERE: the same code has to run
     * on MySQL, MariaDB and SQLite (D12's rule, applied to JSON instead of geo).
     */
    public function usageCount(): int
    {
        return Media::query()
            ->whereNot('collection_name', self::COLLECTION)
            ->get(['collection_name', 'custom_properties'])
            ->filter(fn (Media $media): bool => (int) ($media->getCustomProperty(self::USAGE_PROPERTY) ?? 0) === $this->id)
            ->count();
    }
}
