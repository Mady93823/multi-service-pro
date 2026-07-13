<?php

namespace App\Models;

use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A newsletter signup (M19). Unsubscribing keeps the row and stamps
 * `unsubscribed_at` — deleting it would let the same address be re-added by a
 * later signup and lose the fact that it opted out.
 *
 * @property string $email
 * @property Carbon|null $unsubscribed_at
 */
class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    protected $fillable = ['email', 'source', 'unsubscribed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }
}
