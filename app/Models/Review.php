<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Larastan types columns from migrations and misses casts() — these
 * annotations restore the cast types (same fix as Booking / ProviderProfile).
 *
 * @property int $rating
 * @property bool $is_hidden
 */
class Review extends Model implements HasMedia
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'booking_id',
        'customer_id',
        'provider_id',
        'rating',
        'comment',
        // is_hidden / hidden_reason are deliberately not mass assignable —
        // only ModerateReview sets them.
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_hidden' => 'boolean',
        ];
    }

    /**
     * Review photos live on the private disk (07-Conventions upload rules) —
     * served through the visibility-checked photo route only, so hiding a
     * review hides its photos too.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('review_photos')->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(320)->format('webp');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * What the public (storefront, provider dashboard) is allowed to see.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }
}
