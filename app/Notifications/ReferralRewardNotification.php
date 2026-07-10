<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the referrer their reward landed in the wallet (M12): stored in-app,
 * pushed live over Reverb, and — once Firebase is configured — as FCM push.
 */
class ReferralRewardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Referral $referral)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (FcmChannel::isConfigured()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$title, $body] = $this->message();

        return [
            'type' => 'referral_reward',
            'referral_id' => $this->referral->id,
            'amount' => (string) $this->referral->reward_amount,
            'title' => $title,
            'body' => $body,
            'url' => route('wallet.show'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        [$title, $body] = $this->message();

        return ['title' => $title, 'body' => $body, 'url' => route('wallet.show')];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        return [
            __('Referral reward earned'),
            __('Your referral completed their first booking — :amount was added to your wallet.', [
                'amount' => (string) $this->referral->reward_amount,
            ]),
        ];
    }
}
