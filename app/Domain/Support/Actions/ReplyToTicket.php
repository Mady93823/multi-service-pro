<?php

namespace App\Domain\Support\Actions;

use App\Domain\Support\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\TicketReplyNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplyToTicket
{
    /**
     * The policy already blocks replies on closed tickets; the action
     * re-checks under a lock so two tabs can't race a close. A staff reply
     * flips the ticket to `pending` (waiting on the user) and auto-assigns
     * the first responder; a user reply flips it back to `open` — including
     * reopening a `resolved` ticket.
     *
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(SupportTicket $ticket, User $author, string $body, array $attachments = []): SupportTicketMessage
    {
        $isStaff = $author->hasRole('admin');

        $message = DB::transaction(function () use ($ticket, $author, $body, $attachments, $isStaff): SupportTicketMessage {
            /** @var SupportTicket $locked */
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'body' => __('This ticket is closed and can no longer be replied to.'),
                ]);
            }

            $message = SupportTicketMessage::query()->create([
                'ticket_id' => $locked->id,
                'user_id' => $author->id,
                'body' => $body,
                'is_staff' => $isStaff,
            ]);

            foreach ($attachments as $attachment) {
                $message->addMedia($attachment)->toMediaCollection('attachments');
            }

            $locked->update([
                'status' => $isStaff ? TicketStatus::Pending : TicketStatus::Open,
                'last_reply_at' => now(),
                'assigned_to' => $isStaff ? ($locked->assigned_to ?? $author->id) : $locked->assigned_to,
            ]);

            return $message;
        });

        $ticket->refresh();

        // Notify the other side of the thread (afterCommit on the notification).
        if ($isStaff) {
            $ticket->user?->notify(new TicketReplyNotification($ticket));
        } else {
            $ticket->assignee?->notify(new TicketReplyNotification($ticket));
        }

        return $message;
    }
}
