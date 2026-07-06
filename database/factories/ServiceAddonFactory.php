<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceAddon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceAddon>
 */
class ServiceAddonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'price' => fake()->numberBetween(49, 999),
            'is_active' => true,
        ];
    }
}
