<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'state' => fake()->word(),
            'timezone' => 'Asia/Kolkata',
            'center_lat' => 12.9716,
            'center_lng' => 77.5946,
            'is_active' => true,
            'cash_enabled' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function cashDisabled(): static
    {
        return $this->state(fn () => ['cash_enabled' => false]);
    }

    public function timezone(string $timezone): static
    {
        return $this->state(fn () => ['timezone' => $timezone]);
    }
}
