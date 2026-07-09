<?php

namespace App\Notifications;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Booking lifecycle update delivered to the customer (M11): stored in-app,
 * pushed live over Reverb, and — once Firebase is configured — as FCM push.
 * Queued so the state-machine request never waits on delivery.
 */
class BookingStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $status,
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
            'type' => 'booking_status',
            'booking_id' => $this->booking->id,
            'code' => $this->booking->code,
            'status' => $this->status->value,
            'title' => $title,
            'body' => $body,
            'url' => route('bookings.show', $this->booking->id),
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

        return ['title' => $title, 'body' => $body, 'url' => route('bookings.show', $this->booking->id)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        $code = $this->booking->code;

        return match ($this->status) {
            BookingStatus::Assigned => [__('Professional assigned'), __('A professional was assigned to :code.', ['code' => $code])],
            BookingStatus::Accepted => [__('Booking confirmed'), __('A professional confirmed your booking :code.', ['code' => $code])],
            BookingStatus::EnRoute => [__('On the way'), __('Your professional is heading to your address.')],
            BookingStatus::Arrived => [__('Professional arrived'), __('Your professional has arrived for :code.', ['code' => $code])],
            BookingStatus::InProgress => [__('Service started'), __('Work has started on :code.', ['code' => $code])],
            BookingStatus::Completed => [__('Service complete'), __('Your booking :code is complete.', ['code' => $code])],
            BookingStatus::CancelledProvider => [__('Booking cancelled'), __('Your booking :code was cancelled by the professional.', ['code' => $code])],
            BookingStatus::CancelledAdmin => [__('Booking cancelled'), __('Your booking :code was cancelled.', ['code' => $code])],
            default => [__('Booking updated'), __('Your booking :code was updated.', ['code' => $code])],
        };
    }
}
