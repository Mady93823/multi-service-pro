<?php

namespace Database\Factories;

use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutAccount>
 */
class PayoutAccountFactory extends Factory
{
    protected $model = PayoutAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => User::factory(),
            'type' => 'upi',
            'label' => 'Primary',
            'account_name' => null,
            'account_number' => null,
            'ifsc' => null,
            'upi_id' => fake()->userName().'@upi',
            'is_default' => true,
            'is_verified' => false,
            'verified_at' => null,
        ];
    }

    public function bank(): self
    {
        return $this->state(fn (): array => [
            'type' => 'bank',
            'account_name' => fake()->name(),
            'account_number' => (string) fake()->numerify('##############'),
            'ifsc' => strtoupper(fake()->bothify('????0######')),
            'upi_id' => null,
        ]);
    }

    public function verified(): self
    {
        return $this->state(fn (): array => ['is_verified' => true, 'verified_at' => now()]);
    }
}
