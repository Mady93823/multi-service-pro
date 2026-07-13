<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One payment attempt/settlement against a booking (M08). Amounts are
 * snapshots; rows are never deleted — a failed attempt stays on record.
 *
 * @property PaymentProvider $gateway
 * @property PaymentState $status
 * @property string $amount
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $captured_at
 * @property Carbon|null $reviewed_at
 */
class Payment extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'booking_id',
        'gateway',
        'gateway_ref',
        'bank_account_id',
        'reference',
        'amount',
        'currency',
        'status',
        'payload',
        'captured_at',
        'reviewed_by',
        'reviewed_at',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentProvider::class,
            'status' => PaymentState::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'captured_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The customer's transfer proof (M22): a screenshot or a PDF receipt. It is
     * customer data — private disk, served only through the policy-checked
     * proof route, and no conversions (a PDF would choke them).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof')->useDisk('local')->singleFile();
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Offline rows awaiting an admin decision (M22): the payments-hub queue.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query
            ->where('gateway', PaymentProvider::Offline->value)
            ->where('status', PaymentState::Initiated->value);
    }
}
