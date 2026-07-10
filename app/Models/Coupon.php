<?php

namespace App\Models;

use App\Domain\Coupons\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CouponType $type
 * @property string $value
 * @property string|null $max_discount
 * @property string|null $min_order
 * @property int|null $usage_limit
 * @property int|null $per_user_limit
 * @property bool $first_order_only
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount',
        'min_order',
        'usage_limit',
        'per_user_limit',
        'first_order_only',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'first_order_only' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
