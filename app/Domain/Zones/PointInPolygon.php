<?php

namespace App\Domain\Zones;

/**
 * Ray-casting point-in-polygon test over a GeoJSON Polygon geometry.
 * Runs in PHP instead of MySQL ST_Contains so zone checks behave the same
 * on MySQL 8, MariaDB (shared hosting) and sqlite (tests) — ADR D12.
 * GeoJSON positions are [lng, lat]; ring 0 is the outer boundary, any
 * further rings are holes.
 */
final class PointInPolygon
{
    /**
     * @param  array<string, mixed>  $geometry  GeoJSON Polygon geometry
     */
    public static function contains(array $geometry, float $lat, float $lng): bool
    {
        if (($geometry['type'] ?? null) !== 'Polygon') {
            return false;
        }

        $rings = $geometry['coordinates'] ?? null;

        if (! is_array($rings) || $rings === [] || ! is_array($rings[0] ?? null)) {
            return false;
        }

        if (! self::inRing($rings[0], $lat, $lng)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (is_array($hole) && self::inRing($hole, $lat, $lng)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $ring  list of [lng, lat] positions
     */
    private static function inRing(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            if (! is_array($ring[$i]) || ! is_array($ring[$j])) {
                return false;
            }

            $xi = (float) $ring[$i][0];
            $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0];
            $yj = (float) $ring[$j][1];

            $crossesLatitude = ($yi > $lat) !== ($yj > $lat);

            if ($crossesLatitude && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
