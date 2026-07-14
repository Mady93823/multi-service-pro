<?php

namespace App\Models;

use Database\Factories\SmsLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Delivery audit for one SMS (M23). Append-only — "the customer says they never
 * got it" is a support ticket, and this is the answer to it.
 *
 * @property string $phone
 * @property string $event_key
 * @property string $body
 * @property string $gateway
 * @property string $status
 * @property array<string, mixed>|null $response
 * @property Carbon $created_at
 *
 * @method static Builder<static> query()
 */
class SmsLog extends Model
{
    /** @use HasFactory<SmsLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'phone',
        'event_key',
        'body',
        'gateway',
        'status',
        'response',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
