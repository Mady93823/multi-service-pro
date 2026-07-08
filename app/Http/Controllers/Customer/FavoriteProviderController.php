<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Actions\ToggleFavoriteProvider;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteProviderController extends Controller
{
    public function toggle(Request $request, User $provider, ToggleFavoriteProvider $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($provider->hasRole(Role::Provider->value), 404);

        $favorited = $action->handle($user, $provider);

        return back()->with('success', $favorited
            ? __('Provider added to your favorites.')
            : __('Provider removed from your favorites.'));
    }
}
