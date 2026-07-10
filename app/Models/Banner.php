<?php

namespace App\Models;

use App\Domain\Banners\Enums\BannerPlacement;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Admin-managed marketing images — public disk on purpose (not user
 * uploads), served straight from storage like service images.
 *
 * @property BannerPlacement $placement
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Banner extends Model implements HasMedia
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'link_url',
        'placement',
        'sort_order',
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
            'placement' => BannerPlacement::class,
            'sort_order' => 'integer',
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
        $this->addMediaConversion('hero')->nonQueued()->width(1600)->format('webp');
        $this->addMediaConversion('card')->nonQueued()->width(800)->format('webp');
    }

    /**
     * Active and inside its schedule window — what the storefront shows.
     *
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
