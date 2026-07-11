<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Return leg of "login as" (M13). Verifies the stashed admin still exists and
 * still holds the admin role before handing the session back — if not, the
 * whole session is destroyed rather than restored. Audited via mustLog() and
 * session-regenerated, same as the start leg.
 */
class StopImpersonation
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * Returns the restored admin, or null when the session had to be killed.
     */
    public function handle(Request $request): ?User
    {
        $adminId = $request->session()->get(StartImpersonation::SESSION_KEY);

        abort_if($adminId === null, 403);

        $admin = User::query()->find($adminId);
        $current = $request->user();

        if ($admin === null || ! $admin->hasRole('admin')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        $this->log->mustLog($admin, 'impersonation.stop', $current, ['ip' => (string) $request->ip()]);

        $request->session()->forget(StartImpersonation::SESSION_KEY);
        Auth::login($admin);
        $request->session()->regenerate();

        return $admin;
    }
}
