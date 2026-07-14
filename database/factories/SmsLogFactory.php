<?php

namespace Database\Factories;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\SmsLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
{
    protected $model = SmsLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'phone' => '9876543210',
            'event_key' => NotificationEvent::BookingStatus->value,
            'body' => 'Your booking is confirmed.',
            'gateway' => 'msg91',
            'status' => SmsLog::STATUS_SENT,
            'response' => [],
            'created_at' => now(),
        ];
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => SmsLog::STATUS_FAILED,
            'response' => ['error' => 'HTTP 500'],
        ]);
    }
}
