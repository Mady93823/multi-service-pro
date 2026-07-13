<?php

namespace App\Domain\Support\Actions;

use App\Domain\Support\Enums\TicketCategory;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The public contact form (M19) opens a support ticket — the helpdesk (M16) is
 * the inbox, and a second one would be a second place to forget to look.
 *
 * A signed-in visitor gets an ordinary owned ticket, visible in their own Help
 * section. A guest gets a ticket with no owner and the name/email they typed:
 * admin-only, because there is no account it could belong to. Replying to a
 * guest by email arrives with M23 (mail is still deferred, D14) — until then
 * the admin has the address on the ticket.
 */
class OpenContactTicket
{
    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $data
     */
    public function handle(array $data, ?User $user = null): SupportTicket
    {
        return DB::transaction(function () use ($data, $user): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'code' => Str::uuid()->toString(), // placeholder until the id exists
                'user_id' => $user?->id,
                'guest_name' => $user === null ? $data['name'] : null,
                'guest_email' => $user === null ? $data['email'] : null,
                'subject' => $data['subject'],
                'category' => TicketCategory::Other->value,
                'last_reply_at' => now(),
            ]);

            $ticket->update(['code' => sprintf('TKT-%06d', $ticket->id)]);

            SupportTicketMessage::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user?->id,
                'body' => $data['message'],
                'is_staff' => false,
            ]);

            return $ticket;
        });
    }
}
