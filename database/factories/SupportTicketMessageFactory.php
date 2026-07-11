<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_staff' => false,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (): array => ['is_staff' => true]);
    }
}
