<?php

namespace Database\Factories;

use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Deactivated account (blocked from logging in).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function customer(): static
    {
        return $this->withRole(Role::Customer);
    }

    public function provider(): static
    {
        return $this->withRole(Role::Provider);
    }

    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    protected function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            $user->assignRole(SpatieRole::findOrCreate($role->value));
        });
    }
}
