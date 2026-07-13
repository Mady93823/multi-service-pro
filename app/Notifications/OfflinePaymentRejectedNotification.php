<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The admin could not match an offline transfer (M22). The booking is still
 * awaiting payment, so the customer needs to know now — before the payment
 * window closes and the booking expires.
 */
class OfflinePaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {
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
            'type' => 'offline_payment_rejected',
            'payment_id' => $this->payment->id,
            'booking_id' => $this->payment->booking_id,
            'reason' => $this->reason,
            'title' => $title,
            'body' => $body,
            'url' => $this->url(),
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

        return ['title' => $title, 'body' => $body, 'url' => $this->url()];
    }

    private function url(): string
    {
        return route('bookings.pay', $this->payment->booking_id);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        return [
            __('Payment could not be verified'),
            __('We could not confirm your transfer for booking :code. Reason: :reason', [
                'code' => (string) $this->payment->booking?->code,
                'reason' => $this->reason,
            ]),
        ];
    }
}
