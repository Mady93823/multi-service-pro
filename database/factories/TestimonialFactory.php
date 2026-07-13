<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => null,
            'name' => $this->faker->name(),
            'role' => 'Customer',
            'quote' => $this->faker->sentence(14),
            'rating' => 5,
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
