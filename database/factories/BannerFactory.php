<?php

namespace Database\Factories;

use App\Domain\Banners\Enums\BannerPlacement;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'link_url' => null,
            'placement' => BannerPlacement::HomeHero,
            'sort_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function strip(): static
    {
        return $this->state(fn (): array => ['placement' => BannerPlacement::HomeStrip]);
    }

    public function scheduledOut(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(10),
        ]);
    }
}
