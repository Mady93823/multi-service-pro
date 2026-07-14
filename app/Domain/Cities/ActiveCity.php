<?php

namespace App\Domain\Cities;

use App\Models\Address;
use App\Models\City;
use App\Models\User;

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
     * The zone gate for a browsing customer: their default address's zone, but
     * only while they are browsing the city that address sits in. Someone who
     * switched to another town must see that town's catalog, not their own
     * street's — otherwise the switcher would silently do nothing.
     */
    public function zoneIdFor(?User $user, ?City $city): ?int
    {
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
