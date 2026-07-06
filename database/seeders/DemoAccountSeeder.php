<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Seed one demo login per role. Idempotent.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Demo Admin', 'email' => 'admin@demo.test', 'role' => Role::Admin],
            ['name' => 'Demo Provider', 'email' => 'provider@demo.test', 'role' => Role::Provider],
            ['name' => 'Demo Customer', 'email' => 'customer@demo.test', 'role' => Role::Customer],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']->value]);
        }
    }
}
