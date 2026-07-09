<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment attempt/settlement against a booking (M08). Amounts are
 * snapshots; rows are never deleted — a failed attempt stays on record.
 *
 * @property PaymentProvider $gateway
 * @property PaymentState $status
 * @property string $amount
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $captured_at
 */
class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'gateway',
        'gateway_ref',
        'amount',
        'currency',
        'status',
        'payload',
        'captured_at',
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
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
