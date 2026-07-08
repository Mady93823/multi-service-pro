<?php

namespace App\Domain\Dispatch;

use App\Domain\Dispatch\Contracts\DispatchStrategy;
use App\Domain\Dispatch\Enums\DispatchMode;
use App\Domain\Dispatch\Strategies\BroadcastStrategy;
use App\Domain\Dispatch\Strategies\NearestStrategy;

class StrategyFactory
{
    public function make(DispatchMode $mode): DispatchStrategy
    {
        return match ($mode) {
            DispatchMode::Broadcast => new BroadcastStrategy,
            default => new NearestStrategy,
        };
    }
}
