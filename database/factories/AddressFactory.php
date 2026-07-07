<?php

namespace Database\Factories;

use App\Domain\Addresses\Enums\AddressLabel;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => AddressLabel::Home,
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'postal_code' => (string) fake()->numberBetween(100000, 999999),
            'lat' => 12.9716,
            'lng' => 77.5946,
            'zone_id' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(fn () => ['lat' => $lat, 'lng' => $lng]);
    }
}
