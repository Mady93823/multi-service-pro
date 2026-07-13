<?php

namespace App\Models;

use Database\Factories\PayoutAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Where a provider's payout is sent (M22). Replaces the free-text bank block
 * M09 asked for on every payout request — the details are stored once, an
 * admin can verify them once, and the request snapshots what it used.
 *
 * @property bool $is_default
 * @property bool $is_verified
 * @property Carbon|null $verified_at
 */
class PayoutAccount extends Model
{
    /** @use HasFactory<PayoutAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'type',
        'label',
        'account_name',
        'account_number',
        'ifsc',
        'upi_id',
        'is_default',
        'is_verified',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
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
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * The snapshot a payout request stores, so a later edit to this account
     * cannot rewrite the history of money that already left (M09's rule).
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return $this->type === 'upi'
            ? ['method' => 'upi', 'upi_id' => $this->upi_id]
            : [
                'method' => 'bank',
                'account_name' => $this->account_name,
                'account_number' => $this->account_number,
                'ifsc' => $this->ifsc,
            ];
    }
}
