<?php

namespace App\Domain\Support\Actions;

use App\Domain\Activity\ActivityLogger;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignTicket
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(SupportTicket $ticket, ?User $assignee, User $actor): SupportTicket
    {
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages([
                'assigned_to' => __('This ticket is closed and can no longer be assigned.'),
            ]);
        }

        $ticket->update(['assigned_to' => $assignee?->id]);

        $this->activity->log($actor, 'support.ticket.assigned', $ticket, [
            'assignee_id' => $assignee?->id,
        ]);

        return $ticket;
    }
}
