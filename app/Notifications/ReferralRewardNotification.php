<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\Referral;

/**
 * Tells the referrer their reward landed in the wallet (M12).
 */
class ReferralRewardNotification extends PlatformNotification
{
    public function __construct(public readonly Referral $referral)
    {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::ReferralReward;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'referral_reward',
            'referral_id' => $this->referral->id,
            'amount' => (string) $this->referral->reward_amount,
            'title' => __('Referral reward earned'),
            'body' => __('Your referral completed their first booking — :amount was added to your wallet.', [
                'amount' => (string) $this->referral->reward_amount,
            ]),
            'url' => route('wallet.show'),
        ];
    }
}
