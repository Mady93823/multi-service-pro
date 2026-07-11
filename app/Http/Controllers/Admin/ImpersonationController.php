<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\StartImpersonation;
use App\Domain\Admin\Actions\StopImpersonation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating — sits inside the `role:admin` group.
     */
    public function store(Request $request, User $user, StartImpersonation $start): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $start->handle($request, $admin, $user);

        $home = $user->hasRole('provider') ? route('provider.dashboard') : route('home');

        return redirect($home)->with('success', __('You are now browsing as :name.', ['name' => $user->name]));
    }

    /**
     * Stop impersonating — deliberately OUTSIDE the admin group (the current
     * session belongs to the impersonated customer/provider). Guarded by the
     * session key inside the action.
     */
    public function destroy(Request $request, StopImpersonation $stop): RedirectResponse
    {
        $admin = $stop->handle($request);

        if ($admin === null) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.dashboard')->with('success', __('Back to your admin session.'));
    }
}
