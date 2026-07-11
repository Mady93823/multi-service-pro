<?php

namespace App\Domain\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Sole writer for the append-only activity log (M13). Everything an admin does
 * that changes state goes through here: manual booking transitions, refunds,
 * payout decisions, settings saves, provider reviews, impersonation start/stop.
 *
 * Logging must never break the action it records — a failed insert is reported
 * but not rethrown.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(?User $actor, string $action, ?Model $subject = null, array $context = []): ?ActivityLog
    {
        try {
            return ActivityLog::create([
                'actor_id' => $actor?->id,
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'context' => $context === [] ? null : $context,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Security-sensitive actions (impersonation) must not proceed unaudited —
     * this variant lets the insert failure propagate and abort the caller.
     *
     * @param  array<string, mixed>  $context
     */
    public function mustLog(User $actor, string $action, ?Model $subject = null, array $context = []): ActivityLog
    {
        return ActivityLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $context,
        ]);
    }
}
