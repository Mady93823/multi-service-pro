<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Providers\Actions\ToggleOnline;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\StoreBlackoutRequest;
use App\Models\ProviderBlackout;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function toggleOnline(Request $request, ToggleOnline $action): RedirectResponse
    {
        $online = $action->handle($this->profileOf($request));

        return back()->with('success', $online ? __('You are online and visible for jobs.') : __('You are offline.'));
    }

    public function storeBlackout(StoreBlackoutRequest $request): RedirectResponse
    {
        $this->profileOf($request)->blackouts()->create($request->validated());

        return back()->with('success', __('Time off added.'));
    }

    public function destroyBlackout(Request $request, ProviderBlackout $blackout): RedirectResponse
    {
        abort_unless($blackout->provider_profile_id === $this->profileOf($request)->id, 404);

        $blackout->delete();

        return back()->with('success', __('Time off removed.'));
    }

    private function profileOf(Request $request): ProviderProfile
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ProviderProfile|null $profile */
        $profile = $user->providerProfile()->first();

        abort_if($profile === null, 404);

        return $profile;
    }
}
