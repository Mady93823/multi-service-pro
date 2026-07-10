<?php

namespace Database\Factories;

use App\Domain\Earnings\Enums\PayoutStatus;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutRequest>
 */
class PayoutRequestFactory extends Factory
{
    protected $model = PayoutRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => User::factory(),
            'amount' => '1000.00',
            'status' => PayoutStatus::Requested,
            'method_details' => ['method' => 'upi', 'upi_id' => 'provider@upi'],
        ];
    }

    public function status(PayoutStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
