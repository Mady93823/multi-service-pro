<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;

/**
 * The ticket owner is told when support resolves or closes their ticket
 * (M16 → M11). Status is snapshotted at construction — the queued job must
 * describe what happened, not whatever the ticket says later.
 */
class TicketStatusNotification extends PlatformNotification
{
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly TicketStatus $status,
    ) {
        $this->afterCommit();
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::TicketStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_status',
            'ticket_id' => $this->ticket->id,
            'code' => $this->ticket->code,
            'subject' => $this->ticket->subject,
            'title' => $this->title(),
            'body' => $this->ticket->subject,
            'url' => route('support.tickets.show', $this->ticket),
        ];
    }

    private function title(): string
    {
        return $this->status === TicketStatus::Closed
            ? __('Ticket :code was closed', ['code' => $this->ticket->code])
            : __('Ticket :code was resolved', ['code' => $this->ticket->code]);
    }
}
