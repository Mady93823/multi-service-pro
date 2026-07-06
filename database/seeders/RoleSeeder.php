<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles. Idempotent.
     */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
