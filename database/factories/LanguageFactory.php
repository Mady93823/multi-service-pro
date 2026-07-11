<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    protected $model = Language::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // First letter b–z so a factory code can never collide with the
            // seeded "en" row (unique constraint).
            'code' => fake()->unique()->regexify('[b-df-z][a-z]'),
            'name' => ucfirst(fake()->unique()->word()),
            'native_name' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
