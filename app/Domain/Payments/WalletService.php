<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of wallets + wallet_transactions (M08). Every movement
 * locks the wallet row, appends a ledger entry with the running balance and
 * updates the cached balance in the same transaction — so the ledger always
 * reconciles: balance == sum(credits) - sum(debits).
 */
class WalletService
{
    public function for(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $user->id], ['balance' => '0.00']);
    }

    public function balance(User $user): float
    {
        return (float) $this->for($user)->balance;
    }

    /**
     * @param  numeric-string|float  $amount
     */
    public function credit(User $user, float|string $amount, string $type, ?string $referenceType = null, ?int $referenceId = null, ?string $note = null): WalletTransaction
    {
        return $this->apply($user, (float) $amount, 'credit', $type, $referenceType, $referenceId, $note);
    }

    /**
     * @param  numeric-string|float  $amount
     */
    public function debit(User $user, float|string $amount, string $type, ?string $referenceType = null, ?int $referenceId = null, ?string $note = null): WalletTransaction
    {
        return $this->apply($user, (float) $amount, 'debit', $type, $referenceType, $referenceId, $note);
    }

    private function apply(User $user, float $amount, string $direction, string $type, ?string $referenceType, ?int $referenceId, ?string $note): WalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }

        return DB::transaction(function () use ($user, $amount, $direction, $type, $referenceType, $referenceId, $note): WalletTransaction {
            $wallet = $this->for($user);

            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $balance = (float) $locked->balance;
            $next = $direction === 'credit' ? $balance + $amount : $balance - $amount;

            if ($next < 0) {
                throw ValidationException::withMessages([
                    'wallet' => __('Your wallet balance is not enough for this payment.'),
                ]);
            }

            $locked->forceFill(['balance' => number_format($next, 2, '.', '')])->save();

            return $locked->transactions()->create([
                'type' => $type,
                'direction' => $direction,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => number_format($next, 2, '.', ''),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }
}
