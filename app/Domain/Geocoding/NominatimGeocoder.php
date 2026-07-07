<?php

namespace App\Domain\Geocoding;

use App\Support\Geocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Nominatim (OpenStreetMap) geocoder. Usage policy compliance:
 * identifying User-Agent, at most 1 upstream request per second
 * (rate limiter), aggressive result caching.
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
class NominatimGeocoder implements Geocoder
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';

    public function reverse(float $lat, float $lng): ?array
    {
        $key = sprintf('geocode.reverse.%.5F,%.5F', $lat, $lng);

        return Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            $json = $this->request('/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'addressdetails' => 1,
            ]);

            if ($json === null || isset($json['error'])) {
                return null;
            }

            $address = is_array($json['address'] ?? null) ? $json['address'] : [];

            $line1 = trim(implode(' ', array_filter([
                self::str($address, 'house_number'),
                self::str($address, 'road'),
            ], fn (string $part) => $part !== '')));

            return [
                'lat' => (float) ($json['lat'] ?? $lat),
                'lng' => (float) ($json['lon'] ?? $lng),
                'line1' => $line1 !== '' ? $line1 : self::str($json, 'name'),
                'line2' => self::firstOf($address, ['suburb', 'neighbourhood', 'quarter']),
                'city' => self::firstOf($address, ['city', 'town', 'village', 'municipality', 'county']) ?? '',
                'postal_code' => self::str($address, 'postcode'),
                'display_name' => self::str($json, 'display_name'),
            ];
        });
    }

    public function search(string $query, int $limit = 5): array
    {
        $key = 'geocode.search.'.md5(mb_strtolower(trim($query)).'|'.$limit);

        return Cache::remember($key, now()->addDays(7), function () use ($query, $limit) {
            $json = $this->request('/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => $limit,
            ]);

            if ($json === null) {
                return [];
            }

            $results = [];

            foreach ($json as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $results[] = [
                    'lat' => (float) ($row['lat'] ?? 0),
                    'lng' => (float) ($row['lon'] ?? 0),
                    'display_name' => self::str($row, 'display_name'),
                ];
            }

            return $results;
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private function request(string $path, array $query): ?array
    {
        // Nominatim allows at most 1 request/second per application.
        if (! RateLimiter::attempt('nominatim-upstream', 1, static fn (): bool => true, 1)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout(5)
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function userAgent(): string
    {
        return sprintf('%s (%s)', (string) config('app.name'), (string) config('app.url'));
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function str(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $keys
     */
    private static function firstOf(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = self::str($data, $key);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
