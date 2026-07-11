<?php

namespace Database\Factories;

use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TKT-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'category' => TicketCategory::Other,
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Open,
            'last_reply_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => TicketStatus::Pending]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
            'resolution_note' => fake()->sentence(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
            'resolution_note' => fake()->sentence(),
        ]);
    }
}
