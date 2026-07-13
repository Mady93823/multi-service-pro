<?php

namespace App\Domain\Earnings\Actions;

use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A provider's saved payout destination (M22). Editing the details drops the
 * verified flag: an account an admin checked last month is not the account it
 * is now, and money must never leave against unverified-but-still-ticked
 * details.
 */
class SavePayoutAccount
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $provider, array $data, ?PayoutAccount $account = null): PayoutAccount
    {
        return DB::transaction(function () use ($provider, $data, $account): PayoutAccount {
            // Only the fields the chosen type needs — a UPI account must not
            // carry a half-filled bank block (M09's methodDetails rule).
            $fields = ($data['type'] ?? 'upi') === 'upi'
                ? ['type', 'label', 'upi_id', 'is_default']
                : ['type', 'label', 'account_name', 'account_number', 'ifsc', 'is_default'];

            $attributes = array_intersect_key($data, array_flip($fields)) + [
                'is_verified' => false,
                'verified_at' => null,
            ];

            if ($account === null) {
                $attributes['provider_id'] = $provider->id;
                $account = PayoutAccount::query()->create($attributes);
            } else {
                $account->update($attributes);
            }

            // Exactly one default: the payout dialog needs a sane pre-selection.
            if ($account->is_default) {
                PayoutAccount::query()
                    ->where('provider_id', $provider->id)
                    ->whereKeyNot($account->id)
                    ->update(['is_default' => false]);
            }

            return $account;
        });
    }
}
