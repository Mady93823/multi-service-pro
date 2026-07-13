<?php

namespace App\Domain\Earnings\Actions;

use App\Domain\Earnings\Enums\PayoutStatus;
use App\Models\PayoutAccount;
use Illuminate\Validation\ValidationException;

/**
 * Deleting an account a payout is still riding on would leave the admin paying
 * a request whose destination just vanished — the FK nulls it, and the snapshot
 * is all that is left. Settled requests keep their snapshot, so they are fine.
 */
class DeletePayoutAccount
{
    public function handle(PayoutAccount $account): void
    {
        $inFlight = $account->payoutRequests()
            ->whereIn('status', array_map(fn (PayoutStatus $status): string => $status->value, PayoutStatus::open()))
            ->exists();

        if ($inFlight) {
            throw ValidationException::withMessages([
                'payout_account' => __('A payout is in progress against this account.'),
            ]);
        }

        $account->delete();
    }
}
