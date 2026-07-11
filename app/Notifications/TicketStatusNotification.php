<?php

namespace App\Notifications;

use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The ticket owner is told when support resolves or closes their ticket
 * (M16 → M11). Status is snapshotted at construction — the queued job must
 * describe what happened, not whatever the ticket says later.
 */
class TicketStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly TicketStatus $status,
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
        return [
            'type' => 'ticket_status',
            'ticket_id' => $this->ticket->id,
            'code' => $this->ticket->code,
            'title' => $this->title(),
            'body' => $this->ticket->subject,
            'url' => route('support.tickets.show', $this->ticket),
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
