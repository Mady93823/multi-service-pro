<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

/**
 * No before() admin bypass on purpose — the closed-tickets-read-only gate
 * must hold for admins too, so reply() checks the status explicitly.
 */
class SupportTicketPolicy
{
    public function view(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->hasRole('admin');
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        return $this->view($user, $ticket);
    }
}
