<?php

namespace App\Http\Concerns;

use App\Domain\Cities\ActiveCity;
use App\Models\City;
use Illuminate\Http\Request;

/**
 * The one place the session is read for the visitor's city (M25). The domain
 * resolver takes plain values — it may not import the request (arch rule) —
 * so the HTTP layer hands it the session id and the user.
 */
trait ResolvesActiveCity
{
    protected function activeCity(Request $request): ?City
    {
        $chosen = $request->hasSession()
            ? $request->session()->get(ActiveCity::SESSION_KEY)
            : null;

        return app(ActiveCity::class)->resolve(
            $request->user(),
            is_numeric($chosen) ? (int) $chosen : null,
        );
    }

    /**
     * The zone gating the catalog for this request: a "use my location" fix in
     * the session, else the customer's default address zone (M25).
     */
    protected function activeZoneId(Request $request, ?City $city): ?int
    {
        $detected = $request->hasSession()
            ? $request->session()->get(ActiveCity::ZONE_SESSION_KEY)
            : null;

        return app(ActiveCity::class)->zoneIdFor(
            $request->user(),
            $city,
            is_numeric($detected) ? (int) $detected : null,
        );
    }
}
