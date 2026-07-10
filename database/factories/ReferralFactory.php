<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_id' => User::factory(),
            'referee_id' => User::factory(),
            'code_used' => strtoupper($this->faker->bothify('????####')),
        ];
    }
}
