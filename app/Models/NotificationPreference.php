<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One switch on the event × channel matrix (M23).
 *
 * `user_id = null` is the platform default an admin sets; a row with a user is
 * that user's own opt-out and wins over the default. No row at all means the
 * shipped default in NotificationEvent::defaults().
 *
 * @property int|null $user_id
 * @property string $event_key
 * @property string $channel
 * @property bool $is_enabled
 *
 * @method static Builder<static> query()
 */
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_key',
        'channel',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
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
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }
}
