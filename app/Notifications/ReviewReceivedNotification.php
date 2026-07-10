<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the provider a customer rated their job (M10): stored in-app, pushed
 * live over Reverb, and — once Firebase is configured — as FCM push. Queued
 * so the review request never waits on delivery.
 */
class ReviewReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Review $review)
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
            'type' => 'review_received',
            'review_id' => $this->review->id,
            'booking_id' => $this->review->booking_id,
            'rating' => $this->review->rating,
            'title' => $title,
            'body' => $body,
            'url' => route('provider.dashboard'),
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

        return ['title' => $title, 'body' => $body, 'url' => route('provider.dashboard')];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        return [
            __('New review'),
            __('A customer rated your job :rating out of 5.', ['rating' => (string) $this->review->rating]),
        ];
    }
}
