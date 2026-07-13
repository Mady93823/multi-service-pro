<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->company().' — Current A/C',
            'account_name' => fake()->company(),
            'account_number' => (string) fake()->numerify('##################'),
            'ifsc' => strtoupper(fake()->bothify('????0######')),
            'upi_id' => fake()->userName().'@upi',
            'notes' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
