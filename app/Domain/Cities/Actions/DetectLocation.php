<?php

namespace App\Domain\Cities\Actions;

use App\Domain\Zones\ZoneResolver;
use App\Models\City;
use App\Models\Zone;

/**
 * Turn a browser GPS fix into the storefront's location (M25/M03).
 *
 * The pin resolves to a zone (the containing one, or the nearest when the
 * visitor is outside every service area); the city is that zone's — a city is
 * only a grouping over zones, never a second geography (D12). Null when the
 * install has no active service area for the pin to land in.
 */
final class DetectLocation
{
    public function __construct(private readonly ZoneResolver $resolver) {}

    /**
     * @return array{zone: Zone, city: City}|null
     */
    public function handle(float $lat, float $lng): ?array
    {
        $zone = $this->resolver->resolveOrNearest($lat, $lng);

        if (! $zone instanceof Zone) {
            return null;
        }

        // scopeActive already required an active city, so this is non-null.
        $city = $zone->city;

        if (! $city instanceof City) {
            return null;
        }

        return ['zone' => $zone, 'city' => $city];
    }
}
