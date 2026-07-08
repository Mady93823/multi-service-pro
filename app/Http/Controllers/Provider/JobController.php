<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Exceptions\IllegalTransition;
use App\Domain\Bookings\Exceptions\InvalidJobOtp;
use App\Domain\Dispatch\Actions\AcceptOffer;
use App\Domain\Dispatch\Actions\DeclineOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\AdvanceBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\DispatchOfferResource;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Bookings that occupy the provider right now — the ones with live action
     * buttons on the job screen.
     *
     * @var list<BookingStatus>
     */
    private const ACTIVE_STATUSES = [
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
    ];

    public function __construct(private readonly BookingStateMachine $machine) {}

    public function index(Request $request): Response
    {
        /** @var User $provider */
        $provider = $request->user();

        $offers = DispatchOffer::query()
            ->open()
            ->where('provider_id', $provider->id)
            ->whereHas('booking', fn ($query) => $query->where('status', BookingStatus::Searching->value))
            ->with(['booking.items', 'booking.customer', 'booking.zone'])
            ->orderByDesc('offered_at')
            ->get();

        $jobs = Booking::query()
            ->where('provider_id', $provider->id)
            ->whereIn('status', array_map(fn (BookingStatus $s): string => $s->value, self::ACTIVE_STATUSES))
            ->with(['items', 'customer', 'zone'])
            ->orderBy('scheduled_at')
            ->get();

        $recent = Booking::query()
            ->where('provider_id', $provider->id)
            ->where('status', BookingStatus::Completed->value)
            ->with(['items', 'customer'])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        return Inertia::render('provider/jobs', [
            'offers' => DispatchOfferResource::collection($offers),
            'jobs' => BookingResource::collection($jobs),
            'recent' => BookingResource::collection($recent),
        ]);
    }

    public function acceptOffer(DispatchOffer $offer, AcceptOffer $action, Request $request): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        $action->handle($offer, $provider);

        return back()->with('success', __('Job accepted.'));
    }

    public function declineOffer(DispatchOffer $offer, DeclineOffer $action, Request $request): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        $action->handle($offer, $provider);

        return back()->with('success', __('Offer declined.'));
    }

    public function advance(AdvanceBookingRequest $request, Booking $booking): RedirectResponse
    {
        /** @var User $provider */
        $provider = $request->user();

        abort_unless($booking->provider_id === $provider->id, 404);

        try {
            $this->machine->transition(
                $booking,
                $request->target(),
                BookingActor::Provider,
                $provider,
                $request->validated('note'),
                $request->validated('otp'),
            );
        } catch (IllegalTransition $exception) {
            throw ValidationException::withMessages(['to' => $exception->getMessage()]);
        } catch (InvalidJobOtp $exception) {
            throw ValidationException::withMessages(['otp' => $exception->getMessage()]);
        }

        return back()->with('success', __('Job updated.'));
    }
}
