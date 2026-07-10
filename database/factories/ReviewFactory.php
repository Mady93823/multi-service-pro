<?php

namespace Database\Factories;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->withProvider()->status(BookingStatus::Completed),
            // A review's parties always match its booking's — derive, never invent.
            'customer_id' => fn (array $attributes): int => (int) Booking::query()
                ->whereKey($attributes['booking_id'])->value('customer_id'),
            'provider_id' => fn (array $attributes): int => (int) Booking::query()
                ->whereKey($attributes['booking_id'])->value('provider_id'),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(12),
            'is_hidden' => false,
            'hidden_reason' => null,
        ];
    }

    public function hidden(string $reason = 'Inappropriate content'): static
    {
        return $this->state(fn (): array => [
            'is_hidden' => true,
            'hidden_reason' => $reason,
        ]);
    }
}
