<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Actions\Unsubscribe;
use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->string('search');

        /** @var LengthAwarePaginator<int, Subscriber> $subscribers */
        $subscribers = Subscriber::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/subscribers/index', [
            'subscribers' => [
                'data' => array_map(fn (Subscriber $subscriber): array => [
                    'id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'source' => $subscriber->source,
                    'subscribed' => $subscriber->unsubscribed_at === null,
                    'created_at' => $subscriber->created_at?->toDateString(),
                ], $subscribers->items()),
                'links' => [
                    'first' => $subscribers->url(1),
                    'last' => $subscribers->url($subscribers->lastPage()),
                    'prev' => $subscribers->previousPageUrl(),
                    'next' => $subscribers->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $subscribers->currentPage(),
                    'from' => $subscribers->firstItem(),
                    'last_page' => $subscribers->lastPage(),
                    'per_page' => $subscribers->perPage(),
                    'to' => $subscribers->lastItem(),
                    'total' => $subscribers->total(),
                    'links' => $subscribers->linkCollection()->toArray(),
                ],
            ],
            'filters' => ['search' => $search],
            'stats' => [
                'total' => Subscriber::query()->count(),
                'subscribed' => Subscriber::query()->subscribed()->count(),
            ],
        ]);
    }

    public function destroy(Subscriber $subscriber, Unsubscribe $action): RedirectResponse
    {
        // Unsubscribe, never delete: the next footer signup would silently
        // re-add a deleted address and lose the opt-out.
        $action->handle($subscriber);

        return back()->with('success', __('Subscriber removed from the list.'));
    }
}
