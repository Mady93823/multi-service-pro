<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A blocked account (M17: `users.is_active = false`) is logged out on its next
 * request. Sits in the `web` group rather than behind `auth`, because a session
 * that is already open is exactly the case a block has to end.
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strictly false: a null attribute means "column default not hydrated",
        // not "blocked" — locking people out on an unset attribute would be the
        // worst possible failure mode for this middleware.
        if ($user !== null && $user->getAttribute('is_active') === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('This account has been blocked. Contact support if you think this is a mistake.'),
            ]);
        }

        return $next($request);
    }
}
