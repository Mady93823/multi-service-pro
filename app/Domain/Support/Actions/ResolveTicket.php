<?php

namespace App\Domain\Support\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\TicketStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveTicket
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(SupportTicket $ticket, string $resolutionNote, User $actor): SupportTicket
    {
        DB::transaction(function () use ($ticket, $resolutionNote, $actor): void {
            /** @var SupportTicket $locked */
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'resolution_note' => __('This ticket is already closed.'),
                ]);
            }

            $locked->update([
                'status' => TicketStatus::Resolved,
                'resolved_at' => now(),
                'resolution_note' => $resolutionNote,
            ]);

            $this->activity->log($actor, 'support.ticket.resolved', $locked);
        });

        $ticket->refresh();

        $ticket->user?->notify(new TicketStatusNotification($ticket, TicketStatus::Resolved));

        return $ticket;
    }
}
