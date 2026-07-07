<?php

namespace Database\Seeders;

use App\Domain\Settings\SettingsRegistry;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed shipped defaults. firstOrCreate keeps admin-changed values
     * intact on reseed — only missing keys are (re)created.
     */
    public function run(): void
    {
        foreach (SettingsRegistry::defaults() as $key => $definition) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => $definition['group'],
                    'type' => $definition['type']->value,
                    'value' => $definition['type']->serialize($definition['value']),
                ],
            );
        }

        app(SettingsRegistry::class)->flush();
    }
}
