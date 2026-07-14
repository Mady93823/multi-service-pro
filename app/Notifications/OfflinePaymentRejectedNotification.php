<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\Payment;

/**
 * The admin could not match an offline transfer (M22). The booking is still
 * awaiting payment, so the customer needs to know now — before the payment
 * window closes and the booking expires.
 */
class OfflinePaymentRejectedNotification extends PlatformNotification
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::OfflinePaymentRejected;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $code = (string) $this->payment->booking?->code;

        return [
            'type' => 'offline_payment_rejected',
            'payment_id' => $this->payment->id,
            'booking_id' => $this->payment->booking_id,
            'code' => $code,
            'reason' => $this->reason,
            'title' => __('Payment could not be verified'),
            'body' => __('We could not confirm your transfer for booking :code. Reason: :reason', [
                'code' => $code,
                'reason' => $this->reason,
            ]),
            'url' => route('bookings.pay', $this->payment->booking_id),
        ];
    }
}
