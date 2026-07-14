<?php

namespace Database\Factories;

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Null user = the platform default row an admin owns.
            'user_id' => null,
            'event_key' => NotificationEvent::BookingStatus->value,
            'channel' => NotificationChannel::Mail->value,
            'is_enabled' => true,
        ];
    }
}
