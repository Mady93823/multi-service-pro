<?php

namespace App\Domain\Referrals\Actions;

use App\Models\Referral;
use App\Models\User;

/**
 * Links a fresh account to the referrer whose code it arrived with. The
 * reward is NOT paid here — that waits for the referee's first completed
 * booking (RewardReferral), so fake sign-ups earn nothing.
 */
class RegisterReferral
{
    public function handle(User $referee, string $code): ?Referral
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return null;
        }

        $referrer = User::query()->where('referral_code', $normalized)->first();

        // Unknown code or self-referral: silently skip — registration
        // already validated existence; this guard is for direct calls.
        if ($referrer === null || $referrer->id === $referee->id) {
            return null;
        }

        if (Referral::query()->where('referee_id', $referee->id)->exists()) {
            return null;
        }

        return Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'code_used' => $normalized,
        ]);
    }
}
