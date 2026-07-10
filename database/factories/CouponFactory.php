<?php

namespace Database\Factories;

use App\Domain\Coupons\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SAVE##??')),
            'type' => CouponType::Flat,
            'value' => '50.00',
            'max_discount' => null,
            'min_order' => null,
            'usage_limit' => null,
            'per_user_limit' => null,
            'first_order_only' => false,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(float $value = 10.0, ?float $maxDiscount = null): static
    {
        return $this->state(fn (): array => [
            'type' => CouponType::Percent,
            'value' => number_format($value, 2, '.', ''),
            'max_discount' => $maxDiscount !== null ? number_format($maxDiscount, 2, '.', '') : null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }
}
