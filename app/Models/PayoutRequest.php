<?php

namespace App\Models;

use App\Domain\Earnings\Enums\PayoutStatus;
use Database\Factories\PayoutRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A provider's claim on their available earnings (M09). The claimed rows point
 * back at this request, so what was paid is always reconstructible.
 *
 * @property PayoutStatus $status
 * @property string $amount
 * @property array<string, mixed> $method_details
 * @property Carbon|null $processed_at
 * @property int|null $earnings_count
 */
class PayoutRequest extends Model
{
    /** @use HasFactory<PayoutRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'payout_account_id',
        'amount',
        'status',
        'method_details',
        'processed_by',
        'processed_at',
        'reference',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'decimal:2',
            'method_details' => 'array',
            'processed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * The stored account this request was made against (M22). `method_details`
     * remains the snapshot — this only says where it came from, and survives
     * the account being deleted as a null.
     *
     * @return BelongsTo<PayoutAccount, $this>
     */
    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    /**
     * @return HasMany<Earning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }
}
