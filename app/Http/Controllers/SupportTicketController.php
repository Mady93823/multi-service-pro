<?php

namespace App\Http\Controllers;

use App\Domain\Support\Actions\OpenTicket;
use App\Domain\Support\Actions\ReplyToTicket;
use App\Http\Requests\Support\OpenTicketRequest;
use App\Http\Requests\Support\ReplyToTicketRequest;
use App\Http\Resources\SupportTicketMessageResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Help centre (M16). One controller for customers and providers — the list
 * is scoped to the authenticated user's own tickets and the page picks its
 * shell from the user's role (same idiom as the notifications page).
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->latest('last_reply_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('support/index', [
            'tickets' => SupportTicketResource::collection($tickets),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('support/create', [
            // Optional prefill from the booking-show "Get help" button.
            'booking_id' => $request->integer('booking') ?: null,
            'bookings' => Booking::query()
                ->where(fn ($query) => $query
                    ->where('customer_id', $user->id)
                    ->orWhere('provider_id', $user->id))
                ->latest()
                ->limit(20)
                ->get(['id', 'code', 'created_at'])
                ->map(fn (Booking $booking): array => [
                    'id' => $booking->id,
                    'code' => $booking->code,
                    'created_at' => $booking->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(OpenTicketRequest $request, OpenTicket $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{subject: string, category: string, priority?: string|null, booking_id?: int|null, message: string} $data */
        $data = $request->safe()->except('attachments');

        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);

        $ticket = $action->handle($user, $data, $attachments);

        return redirect()
            ->route('support.tickets.show', $ticket)
            ->with('success', __('Ticket :code created — our team will get back to you.', ['code' => $ticket->code]));
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['booking:id,code', 'assignee:id,name']);

        return Inertia::render('support/show', [
            'ticket' => SupportTicketResource::make($ticket),
            'messages' => SupportTicketMessageResource::collection(
                $ticket->messages()->with(['author:id,name', 'media'])->oldest()->get(),
            ),
            'can_reply' => $request->user()?->can('reply', $ticket) ?? false,
        ]);
    }

    public function reply(ReplyToTicketRequest $request, SupportTicket $ticket, ReplyToTicket $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);

        $action->handle($ticket, $user, (string) $request->validated('body'), $attachments);

        return back()->with('success', __('Reply sent.'));
    }
}
