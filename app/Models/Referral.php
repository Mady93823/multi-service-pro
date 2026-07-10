<?php

namespace App\Models;

use App\Domain\Referrals\Enums\ReferralStatus;
use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReferralStatus $status
 * @property string|null $reward_amount
 * @property Carbon|null $rewarded_at
 */
class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'referee_id',
        'code_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'reward_amount' => 'decimal:2',
            'rewarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
