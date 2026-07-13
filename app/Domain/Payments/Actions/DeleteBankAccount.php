<?php

namespace App\Domain\Payments\Actions;

use App\Models\BankAccount;
use Illuminate\Validation\ValidationException;

/**
 * An account that money was actually paid into is part of the audit trail — it
 * cannot be deleted, only deactivated (the M12 coupon rule; the FK is the
 * backstop). Deactivating hides it at checkout and leaves history intact.
 */
class DeleteBankAccount
{
    public function handle(BankAccount $account): void
    {
        if ($account->payments()->exists()) {
            throw ValidationException::withMessages([
                'bank_account' => __('This account has payments against it. Deactivate it instead of deleting it.'),
            ]);
        }

        $account->delete();
    }
}
