<?php

namespace Database\Factories;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\EarningType;
use App\Models\Booking;
use App\Models\Earning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Earning>
 */
class EarningFactory extends Factory
{
    protected $model = Earning::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Online-paid job: the platform holds the money, so net is positive.
        return [
            'provider_id' => User::factory(),
            'booking_id' => Booking::factory(),
            'payout_request_id' => null,
            'type' => EarningType::Job,
            'gross' => '500.00',
            'commission' => '100.00',
            'collected_amount' => '0.00',
            'net' => '400.00',
            'commission_rate' => '20.00',
            'status' => EarningStatus::Pending,
            'available_at' => now()->addDays(7),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (): array => [
            'status' => EarningStatus::Available,
            'available_at' => now()->subDay(),
        ]);
    }

    public function paidOut(): static
    {
        return $this->state(fn (): array => ['status' => EarningStatus::PaidOut]);
    }

    /**
     * The provider took the customer's cash at the door, so they owe the
     * platform the commission plus the tax they pocketed.
     */
    public function cash(): static
    {
        return $this->state(fn (): array => [
            'collected_amount' => '590.00',
            'net' => '-190.00',
        ]);
    }
}
