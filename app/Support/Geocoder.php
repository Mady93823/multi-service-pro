<?php

namespace App\Support;

interface Geocoder
{
    /**
     * Address details for a coordinate, or null when unavailable
     * (unknown point, upstream failure, or upstream rate limit).
     *
     * @return array{lat: float, lng: float, line1: string, line2: string|null, city: string, postal_code: string, display_name: string}|null
     */
    public function reverse(float $lat, float $lng): ?array;

    /**
     * Free-text place search.
     *
     * @return list<array{lat: float, lng: float, display_name: string}>
     */
    public function search(string $query, int $limit = 5): array;
}
