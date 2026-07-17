<?php

namespace App\Domain\Cities;

use App\Models\Address;
use App\Models\City;
use App\Models\User;
use App\Models\Zone;

/**
 * Which city a visitor is browsing (M25).
 *
 * Detected from where they actually are — the zone their default address pin
 * fell in — and overridable from the header switcher, because a customer may
 * be booking a service for their parents in another town. The override wins:
 * it is the only thing the visitor said out loud.
 *
 * The catalog and dispatch have been zone-gated since M03/M06. A city is a
 * *grouping* over those zones, never a second geography — nothing here does
 * point-in-polygon, it only reads the zone the pin already resolved to (D12).
 */
class ActiveCity
{
    /**
     * Session key holding the visitor's explicit choice.
     */
    public const SESSION_KEY = 'city_id';

    /**
     * Session key holding the zone a GPS "use my location" fix resolved to
     * (M25). A guest has no address, so this is how a detected service area
     * survives the next request; it is cleared when the city is switched.
     */
    public const ZONE_SESSION_KEY = 'zone_id';

    public function __construct(private readonly CityDirectory $directory) {}

    public function resolve(?User $user, ?int $sessionCityId): ?City
    {
        if ($sessionCityId !== null) {
            $chosen = $this->directory->find($sessionCityId);

            if ($chosen instanceof City) {
                return $chosen;
            }
        }

        $detected = $this->detect($user);

        return $detected ?? $this->directory->default();
    }

    /**
     * The zone gate for a browsing customer.
     *
     * A GPS fix the visitor just took ("use my location") wins — it is the most
     * specific thing they said, and it is the only zone a guest has. It counts
     * only while it belongs to the city being browsed, so a stale detection
     * cannot leak across a city switch. Otherwise it falls back to their default
     * address's zone, and only while browsing the city that address sits in —
     * switch town and you see that town's catalog, not your own street's.
     */
    public function zoneIdFor(?User $user, ?City $city, ?int $sessionZoneId = null): ?int
    {
        if ($sessionZoneId !== null && $city instanceof City) {
            $detected = Zone::query()
                ->active()
                ->whereKey($sessionZoneId)
                ->where('city_id', $city->id)
                ->value('id');

            if ($detected !== null) {
                return (int) $detected;
            }
        }

        $address = $this->defaultAddress($user);

        if (! $address instanceof Address || $address->zone_id === null) {
            return null;
        }

        if ($city instanceof City && $address->zone?->city_id !== $city->id) {
            return null;
        }

        return $address->zone_id;
    }

    private function detect(?User $user): ?City
    {
        $city = $this->defaultAddress($user)?->zone?->city;

        return $city instanceof City && $city->is_active ? $city : null;
    }

    private function defaultAddress(?User $user): ?Address
    {
        return $user?->addresses()
            ->with('zone.city')
            ->where('is_default', true)
            ->first();
    }
}
