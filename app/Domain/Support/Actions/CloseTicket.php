<?php

namespace App\Domain\Support\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\TicketStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseTicket
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Closing is final — there is no reopen path (gate criterion: closed
     * tickets are read-only). The note is optional here because a close can
     * follow an earlier resolve that already carries one.
     */
    public function handle(SupportTicket $ticket, ?string $resolutionNote, User $actor): SupportTicket
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
                'status' => TicketStatus::Closed,
                'closed_at' => now(),
                'resolution_note' => $resolutionNote ?? $locked->resolution_note,
            ]);

            $this->activity->log($actor, 'support.ticket.closed', $locked);
        });

        $ticket->refresh();

        $ticket->user?->notify(new TicketStatusNotification($ticket, TicketStatus::Closed));

        return $ticket;
    }
}
