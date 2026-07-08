<?php

namespace App\Models;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Larastan types columns from migrations and misses casts() — these
 * annotations restore the cast types (same fix as Booking / Address).
 *
 * @property ProviderApprovalStatus $approval_status
 * @property array<string, array{off: bool, start?: string, end?: string}>|null $working_hours
 * @property bool $is_online
 * @property string|null $base_lat
 * @property string|null $base_lng
 * @property string $rating_avg
 */
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'experience_years',
        'base_lat',
        'base_lng',
        'service_radius_km',
        'working_hours',
        // approval_status / approval_note / is_online / rating fields are
        // deliberately not mass assignable — reviews and toggles set them
        // explicitly through their actions.
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'base_lat' => 'decimal:7',
            'base_lng' => 'decimal:7',
            'service_radius_km' => 'integer',
            'working_hours' => 'array',
            'is_online' => 'boolean',
            'approval_status' => ProviderApprovalStatus::class,
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'jobs_completed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProviderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    /**
     * @return HasMany<ProviderBlackout, $this>
     */
    public function blackouts(): HasMany
    {
        return $this->hasMany(ProviderBlackout::class)->orderBy('starts_on');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'provider_categories');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ProviderApprovalStatus::Approved;
    }

    /**
     * Minimum profile an admin can approve: base location, working
     * hours, and at least one service category.
     */
    public function isComplete(): bool
    {
        return $this->base_lat !== null
            && $this->base_lng !== null
            && $this->working_hours !== null
            && $this->categories()->exists();
    }
}
