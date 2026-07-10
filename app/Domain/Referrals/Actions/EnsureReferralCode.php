<?php

namespace App\Domain\Referrals\Actions;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Lazily hands a user their share code — no backfill migration needed and
 * accounts that never open the referral card never get one. forceFill on
 * purpose: the code must never be mass-assignable from a request.
 */
class EnsureReferralCode
{
    public function handle(User $user): string
    {
        if ($user->referral_code !== null) {
            return $user->referral_code;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (User::query()->where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }
}
