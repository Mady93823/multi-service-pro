<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Bookings\Actions\CancelBooking;
use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Exceptions\IllegalTransition;
use App\Domain\Dispatch\Actions\DispatchBooking;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransitionBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\DispatchOfferResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    /**
     * Transitions the admin panel offers. placed→ from payment capture and
     * expired→ from timeouts belong to system actors (M08/M06), so they are
     * not buttons.
     */
    private const ADMIN_TARGETS = [
        BookingStatus::Searching,
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
        BookingStatus::Completed,
        BookingStatus::CancelledAdmin,
    ];

    public function __construct(private readonly BookingStateMachine $machine) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status');
        $search = (string) $request->string('search');

        $bookings = Booking::query()
            ->with(['customer', 'provider'])
            ->withCount('items')
            ->when(
                BookingStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/bookings/index', [
            'bookings' => BookingResource::collection($bookings),
            'filters' => ['status' => $status, 'search' => $search],
            'statuses' => array_map(fn (BookingStatus $case): string => $case->value, BookingStatus::cases()),
        ]);
    }

    public function show(Booking $booking): Response
    {
        $booking->load([
            'customer',
            'provider',
            'zone',
            'items',
            'statusHistory' => fn ($query) => $query->orderBy('created_at'),
            'dispatchOffers' => fn ($query) => $query->with('provider')->latest('offered_at'),
            'media',
        ]);

        $allowed = array_values(array_filter(
            $this->machine->allowedFrom($booking->status),
            fn (BookingStatus $status): bool => in_array($status, self::ADMIN_TARGETS, true),
        ));

        return Inertia::render('admin/bookings/show', [
            'booking' => new BookingResource($booking),
            'offers' => DispatchOfferResource::collection($booking->dispatchOffers),
            'can_dispatch' => in_array($booking->status, [BookingStatus::Placed, BookingStatus::Searching], true),
            'allowed_transitions' => array_map(fn (BookingStatus $status): string => $status->value, $allowed),
            'providers' => User::query()
                ->role(Role::Provider->value)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $provider): array => ['id' => $provider->id, 'name' => $provider->name])
                ->all(),
        ]);
    }

    public function transition(TransitionBookingRequest $request, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $to = BookingStatus::from((string) $request->validated('to'));
        $note = $request->validated('note');

        try {
            if ($to === BookingStatus::CancelledAdmin) {
                $cancel->byAdmin($booking, $user, (string) $note);
            } else {
                if ($to === BookingStatus::Assigned) {
                    $booking->provider_id = (int) $request->validated('provider_id');
                }

                $this->machine->transition($booking, $to, BookingActor::Admin, $user, $note);
            }
        } catch (IllegalTransition $exception) {
            throw ValidationException::withMessages(['to' => $exception->getMessage()]);
        }

        return back()->with('success', __('Booking updated.'));
    }

    /**
     * Kick (or re-kick) auto-dispatch for a placed/searching booking — the
     * manual counterpart to the on-placement listener (M06).
     */
    public function dispatch(Booking $booking, DispatchBooking $action): RedirectResponse
    {
        if (! in_array($booking->status, [BookingStatus::Placed, BookingStatus::Searching], true)) {
            throw ValidationException::withMessages([
                'dispatch' => __('This booking cannot be dispatched from its current status.'),
            ]);
        }

        $action->handle($booking);

        return back()->with('success', __('Dispatch started.'));
    }
}
