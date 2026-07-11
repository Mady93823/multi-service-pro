<?php

namespace App\Models;

use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool $is_staff
 */
class SupportTicketMessage extends Model implements HasMedia
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_staff',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User uploads — private disk, served only through the policy-checked
     * attachment route (mirrors booking problem photos). No conversions:
     * PDFs are allowed and image conversions would choke on them.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }
}
