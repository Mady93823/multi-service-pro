<?php

namespace App\Domain\Earnings;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Models\Earning;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model over the append-only earnings ledger (M09). Every figure is a sum
 * of ledger rows — nothing is cached, so a reversal shows up the moment it is
 * written.
 */
class EarningsLedger
{
    /**
     * `claimable` is what a payout request would be worth right now: released
     * rows not already spoken for by an open request. It goes negative when a
     * provider owes commission on cash jobs.
     *
     * @return array{gross: float, commission: float, net: float, pending: float, available: float, paid_out: float, claimable: float}
     */
    public function summary(User $provider): array
    {
        return [
            'gross' => $this->total($provider, 'gross'),
            'commission' => $this->total($provider, 'commission'),
            'net' => $this->total($provider, 'net'),
            'pending' => $this->netIn($provider, EarningStatus::Pending),
            'available' => $this->netIn($provider, EarningStatus::Available),
            'paid_out' => $this->netIn($provider, EarningStatus::PaidOut),
            'claimable' => $this->claimable($provider),
        ];
    }

    public function claimable(User $provider): float
    {
        return round((float) $this->rows($provider)->claimable()->sum('net'), 2);
    }

    /**
     * @return Builder<Earning>
     */
    private function rows(User $provider): Builder
    {
        return Earning::query()->where('provider_id', $provider->id);
    }

    private function total(User $provider, string $column): float
    {
        return round((float) $this->rows($provider)->sum($column), 2);
    }

    private function netIn(User $provider, EarningStatus $status): float
    {
        return round((float) $this->rows($provider)->where('status', $status->value)->sum('net'), 2);
    }
}
