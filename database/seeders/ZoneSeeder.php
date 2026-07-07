<?php

namespace Database\Seeders;

use App\Domain\Zones\ZoneResolver;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Demo service zones plus a demo-customer address book: one address
     * inside a zone, one outside every zone (shows the "outside service
     * area" state). Idempotent.
     */
    public function run(): void
    {
        foreach ($this->zones() as $data) {
            Zone::query()->updateOrCreate(
                ['name' => $data['name'], 'city' => $data['city']],
                ['geojson' => $data['geojson'], 'is_active' => true],
            );
        }

        $customer = User::query()->where('email', 'customer@demo.test')->first();

        if ($customer === null) {
            return;
        }

        $resolver = app(ZoneResolver::class);

        $addresses = [
            [
                'label' => 'home',
                'line1' => '221 MG Road',
                'line2' => 'Shivaji Nagar',
                'city' => 'Bengaluru',
                'postal_code' => '560001',
                'lat' => 12.9758,
                'lng' => 77.6096,
                'is_default' => true,
            ],
            [
                'label' => 'other',
                'line1' => '14 Sayyaji Rao Road',
                'line2' => null,
                'city' => 'Mysuru',
                'postal_code' => '570001',
                'lat' => 12.2958,
                'lng' => 76.6394,
                'is_default' => false,
            ],
        ];

        foreach ($addresses as $data) {
            $customer->addresses()->updateOrCreate(
                ['line1' => $data['line1']],
                $data + ['zone_id' => $resolver->resolve($data['lat'], $data['lng'])?->id],
            );
        }
    }

    /**
     * @return list<array{name: string, city: string, geojson: array{type: string, coordinates: array<int, array<int, array<int, float>>>}}>
     */
    protected function zones(): array
    {
        return [
            [
                'name' => 'Bengaluru Central',
                'city' => 'Bengaluru',
                'geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [77.5400, 12.9000],
                        [77.6600, 12.9000],
                        [77.6600, 13.0300],
                        [77.5400, 13.0300],
                        [77.5400, 12.9000],
                    ]],
                ],
            ],
            [
                'name' => 'Whitefield',
                'city' => 'Bengaluru',
                'geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [77.7100, 12.9300],
                        [77.7900, 12.9300],
                        [77.7900, 13.0100],
                        [77.7100, 13.0100],
                        [77.7100, 12.9300],
                    ]],
                ],
            ],
        ];
    }
}
