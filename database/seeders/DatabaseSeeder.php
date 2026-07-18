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
        // ProviderSeeder must run before BookingSeeder: completing and
        // reviewing the demo jobs updates provider_profiles via listeners
        // (M10), which no-op if the profile row does not exist yet.
        $this->call([
            RoleSeeder::class,
            SettingsSeeder::class,
            DemoAccountSeeder::class,
            CatalogSeeder::class,
            // Drawn covers for the event tree — no-op under tests and without GD.
            EventImageSeeder::class,
            // A zone belongs to a city (M25), so the cities exist first.
            CitySeeder::class,
            ZoneSeeder::class,
            ProviderSeeder::class,
            PaymentSeeder::class,
            BookingSeeder::class,
            MarketingSeeder::class,
            CmsSeeder::class,
            BlogSeeder::class,
            SupportSeeder::class,
        ]);
    }
}
