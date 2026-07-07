<?php

namespace App\Domain\Zones;

use App\Models\Zone;

class ZoneResolver
{
    /**
     * First active zone whose polygon contains the point, or null when
     * the point is outside every service area.
     */
    public function resolve(float $lat, float $lng): ?Zone
    {
        return Zone::query()
            ->active()
            ->get()
            ->first(fn (Zone $zone) => $zone->contains($lat, $lng));
    }
}
