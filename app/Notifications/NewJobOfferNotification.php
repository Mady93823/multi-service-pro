<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A dispatch offer reached this provider (M06 → M11). Drives the Jobs badge
 * and a live toast so an online provider sees offers without refreshing.
 */
class NewJobOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Booking $booking)
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
        return [
            'type' => 'job_offer',
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'title' => __('New job offer'),
            'body' => __('A new booking near you is available — respond before it expires.'),
            'url' => route('provider.jobs.index'),
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
        return [
            'title' => __('New job offer'),
            'body' => __('A new booking near you is available — respond before it expires.'),
            'url' => route('provider.jobs.index'),
        ];
    }
}
