<?php

namespace Database\Factories;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => NotificationEvent::BookingStatus->value,
            'subject' => 'Update on {{ code }}',
            'body' => "Hi {{ name }},\n\n{{ body }}",
            'is_enabled' => true,
        ];
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
