<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * M05 gate: unapproved providers can log in but only see the
 * onboarding screen until an admin approves them.
 */
class EnsureProviderApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || $user->providerProfile()->first()?->isApproved() !== true) {
            return redirect()->route('provider.onboarding');
        }

        return $next($request);
    }
}
