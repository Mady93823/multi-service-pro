<?php

namespace App\Models;

use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Domain\Providers\Enums\ProviderDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ProviderDocumentType $type
 * @property ProviderDocumentStatus $status
 * @property Carbon|null $reviewed_at
 */
class ProviderDocument extends Model
{
    protected $fillable = [
        'type',
        'file_path',
        'status',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProviderDocumentType::class,
            'status' => ProviderDocumentStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
