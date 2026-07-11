<?php

namespace Tests\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;

/**
 * Shared M16 fixtures — a class, not Pest helper functions, for the same
 * --parallel reason as EarningsFixtures (landmine 14).
 */
class SupportFixtures
{
    /**
     * An open ticket with its first message, owned by a role-carrying
     * customer (the support routes sit behind role:customer|provider).
     *
     * @return array{0: SupportTicket, 1: User} ticket, owner
     */
    public static function openTicket(?User $owner = null): array
    {
        $owner ??= User::factory()->customer()->create();

        $ticket = SupportTicket::factory()->create(['user_id' => $owner->id]);

        SupportTicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
        ]);

        return [$ticket, $owner];
    }
}
