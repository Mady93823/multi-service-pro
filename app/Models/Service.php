<?php

namespace App\Models;

use App\Domain\Catalog\Enums\PricingType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Service extends Model implements HasMedia
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'pricing_type',
        'price',
        'duration_minutes',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(200)->format('webp');
        $this->addMediaConversion('card')->nonQueued()->width(600)->format('webp');
        $this->addMediaConversion('hero')->nonQueued()->width(1600)->format('webp');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ServiceAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class);
    }

    /**
     * Admin-curated "people also book" cross-sell links.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_related', 'service_id', 'related_service_id');
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Full-text search on MySQL/MariaDB, LIKE fallback elsewhere (sqlite tests).
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->whereFullText(['name', 'short_description'], $term);
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%");
        });
    }
}
