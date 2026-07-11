<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin "login as" (M13). Security posture, in order:
 *
 *  - only reachable through the `role:admin` route group;
 *  - an admin can never be impersonated, and never by themselves;
 *  - no nesting — one hop, then back;
 *  - the audit row is written BEFORE the swap via mustLog(), so a failed
 *    insert aborts the impersonation instead of leaving it unrecorded;
 *  - the session is regenerated after the swap (fixation guard) and the
 *    "remember me" flag is never carried onto the target's login.
 */
class StartImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(Request $request, User $admin, User $target): void
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            throw ValidationException::withMessages([
                'impersonate' => __('Already impersonating someone — leave that session first.'),
            ]);
        }

        if ($target->id === $admin->id || $target->hasRole('admin')) {
            throw ValidationException::withMessages([
                'impersonate' => __('This account cannot be impersonated.'),
            ]);
        }

        $this->log->mustLog($admin, 'impersonation.start', $target, ['ip' => (string) $request->ip()]);

        $request->session()->put(self::SESSION_KEY, $admin->id);
        Auth::login($target);
        $request->session()->regenerate();
    }
}
