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
