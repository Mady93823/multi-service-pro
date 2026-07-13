<?php

namespace App\Policies;

use App\Domain\Users\Enums\Role;
use App\Models\Payment;
use App\Models\User;

/**
 * Payment proof is customer money data on the private disk (M22). Two parties
 * only: the customer who uploaded it and an admin verifying it — the assigned
 * provider has no business seeing a bank screenshot.
 */
class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::Admin->value) ? true : null;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->booking?->customer_id === $user->id;
    }
}
