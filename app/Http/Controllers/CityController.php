<?php

namespace App\Http\Controllers;

use App\Domain\Cities\ActiveCity;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The storefront city switcher (M25). The choice lives in the session, not on
 * the user: a guest can switch, and it is a browsing preference, not a fact
 * about the account — the address book stays the source of truth for where a
 * customer actually lives.
 */
class CityController extends Controller
{
    public function switch(Request $request, City $city): RedirectResponse
    {
        // An inactive city is not on offer, so it cannot be switched into —
        // otherwise the one control that closes a town could be walked around
        // with a bookmarked URL.
        abort_unless($city->is_active, 404);

        $request->session()->put(ActiveCity::SESSION_KEY, $city->id);

        // A GPS-detected zone belongs to the town it was found in — dropping it
        // here stops a stale service area leaking across an explicit city switch.
        $request->session()->forget(ActiveCity::ZONE_SESSION_KEY);

        return back()->with('success', __('Now showing :city.', ['city' => $city->name]));
    }
}
