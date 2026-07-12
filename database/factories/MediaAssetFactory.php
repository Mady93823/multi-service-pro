<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).'.jpg',
            'uploaded_by' => User::factory()->admin(),
        ];
    }
}
