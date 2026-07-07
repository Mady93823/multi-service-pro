<?php

namespace App\Http\Controllers;

use App\Support\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side proxy for the map pickers, so Nominatim usage policy
 * (rate limit, caching, User-Agent) is enforced in one place and the
 * browser never talks to OSM directly.
 */
class GeocodeController extends Controller
{
    public function reverse(Request $request, Geocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'result' => $geocoder->reverse((float) $validated['lat'], (float) $validated['lng']),
        ]);
    }

    public function search(Request $request, Geocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        return response()->json([
            'results' => $geocoder->search((string) $validated['q']),
        ]);
    }
}
