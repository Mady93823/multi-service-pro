<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\WalletService;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Statuses where something is happening *now* and the customer wants the
     * map, not a list. Kept next to the query that uses it rather than pushed
     * onto the enum: "the provider is on their way" is this screen's question.
     *
     * @var list<BookingStatus>
     */
    private const LIVE = [
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
    ];

    public function __invoke(Request $request, WalletService $wallets): Response
    {
        /** @var User $user */
        $user = $request->user();

        // One query for everything still open, sliced in PHP afterwards. A
        // dashboard that asks the database once per card is the N+1 that
        // QueryBudgetTest exists to catch — and these sets overlap anyway.
        $open = $user->bookings()
            ->whereNotIn('status', $this->terminalValues())
            ->with(['items', 'provider:id,name'])
            ->orderBy('scheduled_at')
            ->get();

        $live = $open->first(fn (Booking $booking): bool => in_array($booking->status, self::LIVE, true));

        return Inertia::render('customer/dashboard', [
            // The one card that replaces the whole screen when it exists: a
            // provider is moving and the customer came here to watch them.
            'live' => $live === null ? null : new BookingResource($live),

            // Money at risk, so it outranks everything else on the page: an
            // unpaid booking is never dispatched and dies on bookings:expire-unpaid.
            'awaiting_payment' => BookingResource::collection(
                $open->filter(fn (Booking $booking): bool => $booking->status === BookingStatus::PendingPayment)->values(),
            ),

            'upcoming' => BookingResource::collection(
                $open->filter(fn (Booking $booking): bool => $booking->status !== BookingStatus::PendingPayment
                    && $booking->id !== $live?->id)
                    ->take(3)
                    ->values(),
            ),

            // M10's prompt, on the screen the customer actually lands on. The
            // booking-show form is the only other place it is offered.
            'to_review' => BookingResource::collection($this->awaitingReview($user)),

            'recent' => BookingResource::collection(
                $user->bookings()
                    ->whereIn('status', [BookingStatus::Completed->value, ...$this->cancelledValues()])
                    ->with(['items', 'provider:id,name'])
                    ->latest('id')
                    ->limit(4)
                    ->get(),
            ),

            'stats' => [
                'completed' => $user->bookings()->where('status', BookingStatus::Completed->value)->count(),
                'upcoming' => $open->count(),
                'wallet_balance' => $wallets->for($user)->balance,
                'addresses' => $user->addresses()->count(),
            ],
        ]);
    }

    /**
     * Completed and unreviewed.
     *
     * @return Collection<int, Booking>
     */
    private function awaitingReview(User $user): Collection
    {
        return $user->bookings()
            ->where('status', BookingStatus::Completed->value)
            ->whereDoesntHave('review')
            ->with(['items', 'provider:id,name'])
            ->latest('completed_at')
            ->limit(2)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function terminalValues(): array
    {
        return array_values(array_map(
            fn (BookingStatus $status): string => $status->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $status): bool => $status->isTerminal()),
        ));
    }

    /**
     * @return list<string>
     */
    private function cancelledValues(): array
    {
        return array_values(array_map(
            fn (BookingStatus $status): string => $status->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $status): bool => $status->isCancellation()),
        ));
    }
}
