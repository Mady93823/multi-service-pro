<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a GeoJSON Polygon geometry as produced by the admin map editor:
 * type "Polygon", at least one linear ring of 4+ closed [lng, lat] positions
 * within valid coordinate ranges.
 */
class GeoJsonPolygon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value['type'] ?? null) !== 'Polygon' || ! is_array($value['coordinates'] ?? null)) {
            $fail(__('Draw the zone boundary on the map before saving.'));

            return;
        }

        $rings = $value['coordinates'];

        if ($rings === []) {
            $fail(__('Draw the zone boundary on the map before saving.'));

            return;
        }

        foreach ($rings as $ring) {
            if (! is_array($ring) || count($ring) < 4) {
                $fail(__('The zone boundary must have at least three points.'));

                return;
            }

            foreach ($ring as $position) {
                if (! is_array($position) || count($position) < 2 || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
                    $fail(__('The zone boundary contains an invalid map point.'));

                    return;
                }

                $lng = (float) $position[0];
                $lat = (float) $position[1];

                if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                    $fail(__('The zone boundary contains an invalid map point.'));

                    return;
                }
            }

            if ($ring[0] !== $ring[count($ring) - 1]) {
                $fail(__('The zone boundary must be a closed shape.'));

                return;
            }
        }
    }
}
