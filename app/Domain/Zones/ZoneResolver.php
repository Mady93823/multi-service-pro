<?php

namespace App\Domain\Zones;

use App\Models\Zone;
use Illuminate\Support\Collection;

class ZoneResolver
{
    private const EARTH_RADIUS_KM = 6371.0;

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

    /**
     * The zone the point falls in, or — when it is outside every service area —
     * the nearest active zone. Powers "use my location" on the storefront: a
     * visitor just off the edge of a service area is shown the area next to
     * them rather than a dead end. Null only when there are no active zones.
     */
    public function resolveOrNearest(float $lat, float $lng): ?Zone
    {
        // Eager-load the city: the caller (DetectLocation) reads $zone->city,
        // and a lazy load is a failure under preventLazyLoading (P7.2).
        /** @var Collection<int, Zone> $zones */
        $zones = Zone::query()->active()->with('city')->get();

        $containing = $zones->first(fn (Zone $zone) => $zone->contains($lat, $lng));

        if ($containing instanceof Zone) {
            return $containing;
        }

        return $zones
            ->map(fn (Zone $zone): array => ['zone' => $zone, 'distance' => $this->distanceKm($zone, $lat, $lng)])
            ->filter(fn (array $row): bool => $row['distance'] !== null)
            ->sortBy('distance')
            ->map(fn (array $row): Zone => $row['zone'])
            ->first();
    }

    /**
     * A rough point-to-zone distance: the Haversine to the nearest vertex of
     * the zone's outer ring. Enough to rank which service area is closest — not
     * a routing distance, and not a point-to-edge minimum (a vertex is close
     * enough at city scale, and far cheaper). Null when the zone has no usable
     * geometry.
     */
    private function distanceKm(Zone $zone, float $lat, float $lng): ?float
    {
        if (! is_array($zone->geojson)) {
            return null;
        }

        $ring = $zone->geojson['coordinates'][0] ?? null;

        if (! is_array($ring) || $ring === []) {
            return null;
        }

        $nearest = null;

        foreach ($ring as $position) {
            if (! is_array($position) || count($position) < 2) {
                continue;
            }

            // GeoJSON positions are [lng, lat].
            $distance = $this->haversineKm($lat, $lng, (float) $position[1], (float) $position[0]);
            $nearest = $nearest === null ? $distance : min($nearest, $distance);
        }

        return $nearest;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }
}
