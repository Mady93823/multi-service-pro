<?php

namespace App\Models;

use App\Domain\Dispatch\Enums\DispatchMode;
use App\Domain\Dispatch\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dispatch offer sent to one provider for one booking (M06). Status changes
 * are driven by the dispatch actions/job — never assign catalog edits here.
 *
 * @property OfferStatus $status
 * @property DispatchMode $strategy
 * @property Carbon|null $offered_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $responded_at
 * @property string|null $distance_km
 */
class DispatchOffer extends Model
{
    protected $fillable = [
        'booking_id',
        'provider_id',
        'strategy',
        'status',
        'round',
        'distance_km',
        'offered_at',
        'expires_at',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strategy' => DispatchMode::class,
            'status' => OfferStatus::class,
            'round' => 'integer',
            'distance_km' => 'decimal:2',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @param  Builder<DispatchOffer>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', OfferStatus::Offered->value);
    }
}
