<?php

namespace App\Models;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Larastan types columns from migrations and misses casts() — these
 * annotations restore the cast types (same fix as Zone::$geojson).
 *
 * @property BookingStatus $status
 * @property PaymentStatus $payment_status
 * @property PaymentMethod $payment_method
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property \Illuminate\Support\Carbon $slot_end_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string $subtotal
 * @property string $addon_total
 * @property string $discount
 * @property string $tax
 * @property string $total
 * @property string|null $cancellation_fee
 * @property array<string, mixed> $address_snapshot
 * @property array<string, mixed>|null $tax_breakup
 */
class Booking extends Model implements HasMedia
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'provider_id',
        'address_id',
        'address_snapshot',
        'zone_id',
        'scheduled_at',
        'slot_end_at',
        'status',
        'subtotal',
        'addon_total',
        'discount',
        'tax',
        'tax_breakup',
        'total',
        'payment_status',
        'payment_method',
        'job_otp_code',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'scheduled_at' => 'datetime',
            'slot_end_at' => 'datetime',
            'status' => BookingStatus::class,
            'subtotal' => 'decimal:2',
            'addon_total' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'tax_breakup' => 'array',
            'total' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'commission_rate_snapshot' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'provider_earning' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Problem photos the customer attaches at checkout; providers add
     * before/after proof in later phases. Private disk — served through
     * the policy-checked photo route only.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('problem_photos')->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(320)->format('webp');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }
}
