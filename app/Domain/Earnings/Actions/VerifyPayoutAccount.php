<?php

namespace App\Domain\Earnings\Actions;

use App\Models\PayoutAccount;

/**
 * Admin ticks (or un-ticks) a provider's payout destination (M22). The flag is
 * advisory — it does not block a payout — but it is the record of a human
 * having compared the account against the KYC documents before paying out.
 * Editing the account clears it again (SavePayoutAccount).
 */
class VerifyPayoutAccount
{
    public function handle(PayoutAccount $account, bool $verified): PayoutAccount
    {
        $account->forceFill([
            'is_verified' => $verified,
            'verified_at' => $verified ? now() : null,
        ])->save();

        return $account;
    }
}
