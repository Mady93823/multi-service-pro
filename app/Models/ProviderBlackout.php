<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class ProviderBlackout extends Model
{
    protected $fillable = [
        'starts_on',
        'ends_on',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ProviderProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id');
    }

    /**
     * Whether the given day falls inside this blackout — M06 dispatch
     * skips providers on blackout.
     */
    public function covers(Carbon $day): bool
    {
        return $day->betweenIncluded($this->starts_on->startOfDay(), $this->ends_on->endOfDay());
    }
}
