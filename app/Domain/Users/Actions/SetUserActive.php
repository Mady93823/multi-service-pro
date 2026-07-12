<?php

namespace App\Domain\Users\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Block / unblock an account (M17). `EnsureUserActive` logs a blocked user out
 * on their next request, so this is the whole enforcement surface.
 *
 * An admin can never be blocked — otherwise the last admin locks the platform
 * out of itself.
 */
class SetUserActive
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, User $user, bool $active, ?string $reason = null): void
    {
        if (! $active && $user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'reason' => __('An administrator account cannot be blocked.'),
            ]);
        }

        $user->forceFill([
            'is_active' => $active,
            'blocked_reason' => $active ? null : $reason,
        ])->save();

        $this->log->log($actor, $active ? 'user.unblocked' : 'user.blocked', $user, [
            'reason' => $active ? null : $reason,
        ]);
    }
}
