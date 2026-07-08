<?php

namespace Database\Factories;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    protected $model = ProviderProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bio' => fake()->sentence(12),
            'experience_years' => fake()->numberBetween(1, 15),
            'base_lat' => 12.9716,
            'base_lng' => 77.5946,
            'service_radius_km' => 10,
            'working_hours' => self::defaultWorkingHours(),
            'is_online' => false,
            'approval_status' => ProviderApprovalStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['approval_status' => ProviderApprovalStatus::Approved]);
    }

    public function rejected(string $note = 'Documents unreadable.'): static
    {
        return $this->state(fn (): array => [
            'approval_status' => ProviderApprovalStatus::Rejected,
            'approval_note' => $note,
        ]);
    }

    public function suspended(string $note = 'Repeated no-shows.'): static
    {
        return $this->state(fn (): array => [
            'approval_status' => ProviderApprovalStatus::Suspended,
            'approval_note' => $note,
        ]);
    }

    public function online(): static
    {
        return $this->approved()->state(fn (): array => ['is_online' => true]);
    }

    /**
     * Mon–Sat 09:00–18:00, Sunday off.
     *
     * @return array<string, array{off: bool, start?: string, end?: string}>
     */
    public static function defaultWorkingHours(): array
    {
        $working = ['off' => false, 'start' => '09:00', 'end' => '18:00'];

        return [
            'mon' => $working,
            'tue' => $working,
            'wed' => $working,
            'thu' => $working,
            'fri' => $working,
            'sat' => $working,
            'sun' => ['off' => true],
        ];
    }
}
