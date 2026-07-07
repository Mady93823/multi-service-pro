<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName(),
            'city' => fake()->city(),
            'geojson' => self::squareAround(12.9716, 77.5946),
            'is_active' => true,
        ];
    }

    /**
     * GeoJSON Polygon square of ±$delta degrees around a center point.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    public static function squareAround(float $lat, float $lng, float $delta = 0.05): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$lng - $delta, $lat - $delta],
                [$lng + $delta, $lat - $delta],
                [$lng + $delta, $lat + $delta],
                [$lng - $delta, $lat + $delta],
                [$lng - $delta, $lat - $delta],
            ]],
        ];
    }

    public function around(float $lat, float $lng, float $delta = 0.05): static
    {
        return $this->state(fn () => ['geojson' => self::squareAround($lat, $lng, $delta)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
