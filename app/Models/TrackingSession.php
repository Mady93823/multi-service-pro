<?php

namespace App\Models;

use App\Domain\Tracking\Enums\TrackingSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One live-journey session per booking (05-Live-Tracking). Holds the
 * last-known checkpoint for the polling fallback; the point trail lives in
 * tracking_points.
 *
 * @property TrackingSessionStatus $status
 * @property string|null $last_lat
 * @property string|null $last_lng
 * @property string|null $last_accuracy_m
 * @property string|null $last_heading
 * @property string|null $last_speed_kmh
 * @property Carbon|null $last_ping_at
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
class TrackingSession extends Model
{
    protected $fillable = [
        'booking_id',
        'provider_id',
        'status',
        'last_lat',
        'last_lng',
        'last_accuracy_m',
        'last_heading',
        'last_speed_kmh',
        'last_ping_at',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TrackingSessionStatus::class,
            'last_lat' => 'decimal:7',
            'last_lng' => 'decimal:7',
            'last_accuracy_m' => 'decimal:2',
            'last_heading' => 'decimal:2',
            'last_speed_kmh' => 'decimal:2',
            'last_ping_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
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
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return HasMany<TrackingPoint, $this>
     */
    public function points(): HasMany
    {
        return $this->hasMany(TrackingPoint::class);
    }

    /**
     * @param  Builder<TrackingSession>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TrackingSessionStatus::Active->value);
    }
}
