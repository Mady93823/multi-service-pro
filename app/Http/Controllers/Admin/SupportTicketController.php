<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\Support\Actions\AssignTicket;
use App\Domain\Support\Actions\CloseTicket;
use App\Domain\Support\Actions\ReplyToTicket;
use App\Domain\Support\Actions\ResolveTicket;
use App\Domain\Support\Enums\TicketPriority;
use App\Domain\Support\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Http\Requests\Admin\CloseTicketRequest;
use App\Http\Requests\Admin\ResolveTicketRequest;
use App\Http\Requests\Support\ReplyToTicketRequest;
use App\Http\Resources\SupportTicketMessageResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->string('status');
        $priority = (string) $request->string('priority');
        $assigned = (string) $request->string('assigned');

        /** @var User $admin */
        $admin = $request->user();

        $tickets = SupportTicket::query()
            ->with(['user:id,name', 'assignee:id,name', 'booking:id,code'])
            ->withCount('messages')
            ->when(TicketStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when(TicketPriority::tryFrom($priority) !== null, fn ($query) => $query->where('priority', $priority))
            ->when($assigned === 'me', fn ($query) => $query->where('assigned_to', $admin->id))
            ->when($assigned === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->latest('last_reply_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/tickets/index', [
            'tickets' => SupportTicketResource::collection($tickets),
            'filters' => [
                'status' => TicketStatus::tryFrom($status)?->value,
                'priority' => TicketPriority::tryFrom($priority)?->value,
                'assigned' => in_array($assigned, ['me', 'unassigned'], true) ? $assigned : null,
            ],
        ]);
    }

    public function show(SupportTicket $ticket, SettingsRegistry $settings): Response
    {
        $ticket->load(['user:id,name,email', 'booking:id,code', 'assignee:id,name']);

        /** @var list<array{title?: string, body?: string}> $canned */
        $canned = $settings->get('support.canned_responses') ?? [];

        return Inertia::render('admin/tickets/show', [
            'ticket' => SupportTicketResource::make($ticket),
            'messages' => SupportTicketMessageResource::collection(
                $ticket->messages()->with(['author:id,name', 'media'])->oldest()->get(),
            ),
            'canned_responses' => $canned,
            'admins' => User::query()
                ->role('admin')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name]),
        ]);
    }

    public function reply(ReplyToTicketRequest $request, SupportTicket $ticket, ReplyToTicket $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);

        $action->handle($ticket, $admin, (string) $request->validated('body'), $attachments);

        return back()->with('success', __('Reply sent.'));
    }

    public function assign(AssignTicketRequest $request, SupportTicket $ticket, AssignTicket $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action->handle($ticket, $request->assignee(), $admin);

        return back()->with('success', __('Ticket assignment updated.'));
    }

    public function resolve(ResolveTicketRequest $request, SupportTicket $ticket, ResolveTicket $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action->handle($ticket, (string) $request->validated('resolution_note'), $admin);

        return back()->with('success', __('Ticket resolved.'));
    }

    public function close(CloseTicketRequest $request, SupportTicket $ticket, CloseTicket $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        /** @var string|null $note */
        $note = $request->validated('resolution_note');

        $action->handle($ticket, $note, $admin);

        return back()->with('success', __('Ticket closed.'));
    }
}
