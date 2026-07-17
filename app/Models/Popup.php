<?php

namespace App\Models;

use App\Domain\Marketing\Enums\PopupAudience;
use Database\Factories\PopupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A scheduled promo modal (M19). Body is markdown; the storefront renders it
 * through MarkdownRenderer, never as raw HTML.
 *
 * @property PopupAudience $audience
 * @property bool $is_active
 * @property int $frequency_days
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Popup extends Model implements HasMedia
{
    /** @use HasFactory<PopupFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'body',
        'link_url',
        'link_label',
        'audience',
        'frequency_days',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => PopupAudience::class,
            'frequency_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->nonQueued()->width(800)->format('webp');
    }

    /**
     * Active and inside its window — the same shape as Banner::scopeLive().
     *
     * @param  Builder<Popup>  $query
     * @return Builder<Popup>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
