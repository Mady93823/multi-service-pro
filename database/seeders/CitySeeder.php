<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Two demo cities (M25) — the module's done-when is that two cities with
 * distinct zones both work end to end, so the demo data has to show it.
 * Runs before ZoneSeeder: a zone cannot exist without its city.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->cities() as $index => $data) {
            City::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data + ['sort_order' => $index, 'is_active' => true],
            );
        }
    }

    /**
     * @return list<array{name: string, slug: string, state: string, timezone: string, center_lat: float, center_lng: float}>
     */
    private function cities(): array
    {
        return [
            [
                'name' => 'Bengaluru',
                'slug' => 'bengaluru',
                'state' => 'Karnataka',
                'timezone' => 'Asia/Kolkata',
                'center_lat' => 12.9716,
                'center_lng' => 77.5946,
            ],
            [
                'name' => 'Mysuru',
                'slug' => 'mysuru',
                'state' => 'Karnataka',
                'timezone' => 'Asia/Kolkata',
                'center_lat' => 12.2958,
                'center_lng' => 76.6394,
            ],
        ];
    }
}
