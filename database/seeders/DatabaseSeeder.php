<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingsSeeder::class,
            DemoAccountSeeder::class,
            CatalogSeeder::class,
            ZoneSeeder::class,
            BookingSeeder::class,
        ]);
    }
}
