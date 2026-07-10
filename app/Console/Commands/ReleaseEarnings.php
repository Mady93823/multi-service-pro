<?php

namespace App\Console\Commands;

use App\Domain\Earnings\Enums\EarningStatus;
use App\Models\Earning;
use Illuminate\Console\Command;

/**
 * Ends the payouts.hold_days window (M09): earnings past their available_at
 * become claimable by a payout request. Runs daily; safe to run any time.
 */
class ReleaseEarnings extends Command
{
    protected $signature = 'earnings:release';

    protected $description = 'Release held provider earnings whose hold window has passed';

    public function handle(): int
    {
        $released = Earning::query()
            ->where('status', EarningStatus::Pending->value)
            ->where('available_at', '<=', now())
            ->update(['status' => EarningStatus::Available->value]);

        $this->info("Released {$released} earning(s).");

        return self::SUCCESS;
    }
}
