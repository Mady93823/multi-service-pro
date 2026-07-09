<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single accepted GPS ping on a journey (05-Live-Tracking). Append-only;
 * pruned after tracking.points_retention_days.
 *
 * @property string $lat
 * @property string $lng
 * @property string|null $accuracy_m
 * @property string|null $speed_kmh
 * @property string|null $heading
 * @property Carbon $recorded_at
 */
class TrackingPoint extends Model
{
    protected $fillable = [
        'tracking_session_id',
        'lat',
        'lng',
        'accuracy_m',
        'speed_kmh',
        'heading',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
            'speed_kmh' => 'decimal:2',
            'heading' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrackingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id');
    }
}
