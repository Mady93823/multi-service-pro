<?php

namespace App\Models;

use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TicketStatus $status
 * @property TicketCategory $category
 * @property TicketPriority $priority
 * @property Carbon|null $last_reply_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 */
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'user_id',
        'booking_id',
        'subject',
        'category',
        'priority',
        'status',
        'assigned_to',
        'resolution_note',
        'last_reply_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenStates(Builder $query): Builder
    {
        return $query->whereNot('status', TicketStatus::Closed->value);
    }
}
