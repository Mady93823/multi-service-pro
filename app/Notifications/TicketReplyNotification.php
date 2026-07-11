<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A new message landed on a support ticket (M16 → M11). Sent to the ticket
 * owner when support replies, and to the assigned admin when the user
 * replies back — the broadcast channel is what makes the ≤2s in-app gate.
 */
class TicketReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket)
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
            'type' => 'ticket_reply',
            'ticket_id' => $this->ticket->id,
            'code' => $this->ticket->code,
            'title' => __('New reply on ticket :code', ['code' => $this->ticket->code]),
            'body' => $this->ticket->subject,
            'url' => $this->url($notifiable),
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
            'title' => __('New reply on ticket :code', ['code' => $this->ticket->code]),
            'body' => $this->ticket->subject,
            'url' => $this->url($notifiable),
        ];
    }

    private function url(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->hasRole('admin')) {
            return route('admin.tickets.show', $this->ticket);
        }

        return route('support.tickets.show', $this->ticket);
    }
}
