<?php

namespace App\Domain\Referrals\Actions;

use App\Domain\Payments\WalletService;
use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Referral;
use App\Notifications\ReferralRewardNotification;
use Illuminate\Support\Facades\DB;

/**
 * Pays the referrer once the referee's first booking completes. The wallet
 * is credited through WalletService only (the sole wallet writer, M08), and
 * the pending→rewarded flip happens under a row lock — a double-fired
 * listener or racing completions cannot pay twice. reward_amount snapshots
 * what was actually credited at reward time (ADR D18).
 */
class RewardReferral
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(Referral $referral): void
    {
        $amount = $this->settings->decimal('referrals.reward_amount', 0.0);

        if ($amount <= 0) {
            return; // reward disabled by config — stays pending
        }

        $rewarded = DB::transaction(function () use ($referral, $amount): ?Referral {
            /** @var Referral $locked */
            $locked = Referral::query()->whereKey($referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReferralStatus::Pending) {
                return null;
            }

            $referrer = $locked->referrer;

            if ($referrer === null) {
                return null;
            }

            $this->wallet->credit(
                $referrer,
                $amount,
                'referral_reward',
                Referral::class,
                $locked->id,
                $locked->referee?->name,
            );

            $locked->forceFill([
                'reward_amount' => number_format($amount, 2, '.', ''),
                'status' => ReferralStatus::Rewarded,
                'rewarded_at' => now(),
            ])->save();

            return $locked;
        });

        $rewarded?->referrer?->notify(new ReferralRewardNotification($rewarded));
    }
}
