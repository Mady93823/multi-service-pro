<?php

namespace App\Domain\Dispatch\Strategies;

use App\Domain\Dispatch\Contracts\DispatchStrategy;
use App\Domain\Dispatch\Enums\DispatchMode;
use Illuminate\Support\Collection;

/**
 * Offer every eligible provider at once — first to accept wins, the rest are
 * expired on accept.
 */
class BroadcastStrategy implements DispatchStrategy
{
    public function mode(): DispatchMode
    {
        return DispatchMode::Broadcast;
    }

    public function pick(Collection $eligible): Collection
    {
        return $eligible->values();
    }
}
