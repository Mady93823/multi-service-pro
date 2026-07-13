<?php

namespace App\Models;

use Database\Factories\PageBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One typed block on a page (M20, ADR D22).
 *
 * `payload` is validated against the block's schema on write, so a renderer
 * never meets a shape it cannot handle. Pictures picked from the library are
 * copied into this block's own `images` collection (D29) — the payload keeps
 * the library asset id, which is how the copy is found again.
 *
 * @property int $id
 * @property int $page_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class PageBlock extends Model implements HasMedia
{
    /** @use HasFactory<PageBlockFactory> */
    use HasFactory, InteractsWithMedia;

    public const COLLECTION = 'images';

    protected $fillable = [
        'type',
        'payload',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(400)->format('webp');
        $this->addMediaConversion('card')->nonQueued()->width(1000)->format('webp');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Active and inside its schedule window — the same shape as Banner/Popup.
     *
     * @param  Builder<PageBlock>  $query
     * @return Builder<PageBlock>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
