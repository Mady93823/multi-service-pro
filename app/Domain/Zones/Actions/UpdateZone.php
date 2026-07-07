<?php

namespace App\Domain\Zones\Actions;

use App\Models\Zone;

class UpdateZone
{
    public function __construct(private ResolveAddressZones $resolveAddresses) {}

    /**
     * @param  array{name: string, city: string, geojson: array<string, mixed>, is_active?: bool}  $data
     */
    public function handle(Zone $zone, array $data): Zone
    {
        $zone->update($data);

        $this->resolveAddresses->handle($zone->id);

        return $zone;
    }
}
