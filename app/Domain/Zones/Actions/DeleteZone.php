<?php

namespace App\Domain\Zones\Actions;

use App\Models\Zone;

class DeleteZone
{
    public function __construct(private ResolveAddressZones $resolveAddresses) {}

    public function handle(Zone $zone): void
    {
        // FK nulls the zone's addresses on delete; re-resolve them in case
        // another active zone also covers those points.
        $zone->delete();

        $this->resolveAddresses->handle();
    }
}
