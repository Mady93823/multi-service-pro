<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A bank/UPI account the platform receives offline payments into (M22). Shown
 * at checkout as the transfer instructions and referenced by the payment row.
 *
 * @property bool $is_active
 */
class BankAccount extends Model implements HasMedia
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'label',
        'account_name',
        'account_number',
        'ifsc',
        'upi_id',
        'notes',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The payment QR is the platform's own marketing-grade image, not customer
     * data — public disk, like a banner (D29's split).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('qr')->useDisk('public')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(400)->format('webp');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @param  Builder<BankAccount>  $query
     * @return Builder<BankAccount>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
