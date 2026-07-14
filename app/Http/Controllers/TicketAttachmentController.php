<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ServesPrivateFiles;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ticket attachments live on the private disk (07-Conventions upload rules) —
 * this policy-checked route is the only way to view them. Mirrors
 * BookingPhotoController, one hop longer: media hangs off the message.
 */
class TicketAttachmentController extends Controller
{
    use ServesPrivateFiles;

    public function show(SupportTicket $ticket, Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $ticket);

        abort_unless(
            $media->model_type === SupportTicketMessage::class
            && $media->collection_name === 'attachments',
            404,
        );

        $belongsToTicket = SupportTicketMessage::query()
            ->whereKey((int) $media->model_id)
            ->where('ticket_id', $ticket->id)
            ->exists();

        abort_unless($belongsToTicket, 404);

        return $this->privateFile($media->getPath(), $media->mime_type, $media->file_name);
    }
}
