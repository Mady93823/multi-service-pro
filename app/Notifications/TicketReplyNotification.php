<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * A new message landed on a support ticket (M16 → M11). Sent to the ticket
 * owner when support replies, and to the assigned admin when the user
 * replies back — the broadcast channel is what makes the ≤2s in-app gate.
 */
class TicketReplyNotification extends PlatformNotification
{
    public function __construct(public readonly SupportTicket $ticket)
    {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::TicketReply;
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
            'subject' => $this->ticket->subject,
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
