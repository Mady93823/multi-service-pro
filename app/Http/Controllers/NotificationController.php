<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app notification centre (M11). Works for every role — the list is scoped
 * to the authenticated user's own notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->paginate(20);

        // Prop name must not be `notifications` — that is the shared bell feed
        // (HandleInertiaRequests) and a page prop would shadow it.
        return Inertia::render('notifications/index', [
            'entries' => $notifications->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
