<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\WalletService;
use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Support's manual wallet correction (M22): a goodwill credit, or a debit that
 * takes back a credit given in error.
 *
 * It writes through `WalletService` like everything else (D15) — so the ledger
 * still reconciles, an overdraw is still refused, and the entry is a normal
 * `adjustment` row rather than a special case. The reason is mandatory at the
 * FormRequest, and the admin who did it is audited by the controller.
 */
class AdjustWallet
{
    public function __construct(private readonly WalletService $wallet) {}

    public function handle(User $customer, string $direction, float $amount, string $reason): WalletTransaction
    {
        return $direction === 'debit'
            ? $this->wallet->debit($customer, $amount, 'adjustment', User::class, $customer->id, $reason)
            : $this->wallet->credit($customer, $amount, 'adjustment', User::class, $customer->id, $reason);
    }
}
