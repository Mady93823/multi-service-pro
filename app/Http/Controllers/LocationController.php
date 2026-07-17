<?php

namespace App\Http\Controllers;

use App\Domain\Cities\Actions\DetectLocation;
use App\Domain\Cities\ActiveCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Use my location" on the storefront (M25/M03). A browser GPS fix is resolved
 * to a service area and remembered in the session — city and, so the catalog is
 * gated to exactly where they are, the zone. Open to guests: a visitor should
 * see what is available near them before creating an account.
 */
class LocationController extends Controller
{
    public function detect(Request $request, DetectLocation $detect): RedirectResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $detect->handle((float) $data['lat'], (float) $data['lng']);

        if ($result === null) {
            return back()->with('error', __('We could not find a service area near you yet.'));
        }

        $request->session()->put(ActiveCity::SESSION_KEY, $result['city']->id);
        $request->session()->put(ActiveCity::ZONE_SESSION_KEY, $result['zone']->id);

        return back()->with('success', __('Showing services near :area.', ['area' => $result['zone']->name]));
    }
}
