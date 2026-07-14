<?php

namespace App\Domain\Zones\Actions;

use App\Models\Zone;

class CreateZone
{
    public function __construct(private ResolveAddressZones $resolveAddresses) {}

    /**
     * @param  array{city_id: int, name: string, geojson: array<string, mixed>, is_active?: bool}  $data
     */
    public function handle(array $data): Zone
    {
        $zone = Zone::create($data);

        $this->resolveAddresses->handle();

        return $zone;
    }
}
