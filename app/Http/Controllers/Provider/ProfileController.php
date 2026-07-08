<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Providers\Actions\UpsertProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdateProviderProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function update(UpdateProviderProfileRequest $request, UpsertProviderProfile $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{bio?: string|null, experience_years?: int|null, base_lat: float, base_lng: float, service_radius_km: int, working_hours: array<string, array{off: bool, start?: string, end?: string}>, category_ids: list<int>} $data */
        $data = $request->validated();

        $action->handle($user, $data);

        return back()->with('success', __('Profile saved.'));
    }
}
