<?php

namespace App\Models;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\EarningType;
use Database\Factories\EarningFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of the provider's append-only ledger (M09). Money columns are
 * snapshots and are never rewritten; a correction is an opposing row.
 *
 * `net` is signed: a cash job is negative because the provider already took
 * the customer's money at the door and owes the platform its commission.
 *
 * @property EarningType $type
 * @property EarningStatus $status
 * @property string $gross
 * @property string $commission
 * @property string $collected_amount
 * @property string $net
 * @property string $commission_rate
 * @property Carbon|null $available_at
 */
class Earning extends Model
{
    /** @use HasFactory<EarningFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'booking_id',
        'payout_request_id',
        'type',
        'gross',
        'commission',
        'collected_amount',
        'net',
        'commission_rate',
        'status',
        'available_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EarningType::class,
            'status' => EarningStatus::class,
            'gross' => 'decimal:2',
            'commission' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'net' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'available_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<PayoutRequest, $this>
     */
    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    /**
     * Rows a payout request may claim: released from the hold window and not
     * already spoken for by another request.
     *
     * @param  Builder<Earning>  $query
     * @return Builder<Earning>
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->where('status', EarningStatus::Available->value)
            ->whereNull('payout_request_id');
    }
}
