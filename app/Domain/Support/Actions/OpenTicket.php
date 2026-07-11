<?php

namespace App\Domain\Support\Actions;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpenTicket
{
    /**
     * The first message is created directly (not via ReplyToTicket) — a
     * fresh ticket is already `open`, and opening must not notify anyone.
     *
     * @param  array{subject: string, category: string, priority?: string|null, booking_id?: int|null, message: string}  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(User $user, array $data, array $attachments = []): SupportTicket
    {
        return DB::transaction(function () use ($user, $data, $attachments): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'code' => Str::uuid()->toString(), // placeholder until the id exists
                'user_id' => $user->id,
                'booking_id' => $data['booking_id'] ?? null,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal',
                'last_reply_at' => now(),
            ]);

            $ticket->update(['code' => sprintf('TKT-%06d', $ticket->id)]);

            $message = SupportTicketMessage::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $data['message'],
                'is_staff' => false,
            ]);

            foreach ($attachments as $attachment) {
                $message->addMedia($attachment)->toMediaCollection('attachments');
            }

            return $ticket;
        });
    }
}
