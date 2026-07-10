<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Actions\CancelBooking;
use App\Domain\Bookings\Actions\RebookBooking;
use App\Domain\Bookings\Actions\RescheduleBooking;
use App\Domain\Bookings\CancellationFeeCalculator;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Invoicing\BookingInvoice;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CancelBookingRequest;
use App\Http\Requests\Customer\RescheduleBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $bookings = $user->bookings()
            ->with(['items'])
            ->latest()
            ->paginate(10);

        return Inertia::render('customer/bookings/index', [
            'bookings' => BookingResource::collection($bookings),
        ]);
    }

    public function show(
        Request $request,
        Booking $booking,
        CancellationFeeCalculator $fees,
        SlotGenerator $slots,
        SettingsRegistry $settings,
        BookingInvoice $invoice,
    ): Response {
        Gate::authorize('view', $booking);

        $booking->load(['items', 'provider', 'zone', 'statusHistory' => fn ($query) => $query->orderBy('created_at'), 'media']);

        $review = $booking->review()->with(['customer:id,name', 'media'])->first();

        $canReschedule = $booking->status->customerReschedulable()
            && CarbonImmutable::now()->lessThanOrEqualTo(
                $booking->scheduled_at->toImmutable()->subHours($settings->integer('booking.reschedule_min_hours', 2)),
            );

        return Inertia::render('customer/bookings/show', [
            'booking' => new BookingResource($booking),
            'abilities' => [
                'can_cancel' => $booking->status->customerCancellable(),
                'can_reschedule' => $canReschedule,
                'can_rebook' => $booking->status->isTerminal(),
                // The pay page is only reachable while money is still owed (M08).
                'can_pay' => $booking->status === BookingStatus::PendingPayment,
                // A tax invoice exists once money has actually changed hands (M09).
                'can_download_invoice' => $invoice->isAvailableFor($booking),
                'cancellation_fee_preview' => $booking->status->customerCancellable()
                    ? $fees->feeFor($booking)
                    : null,
                // One review per completed booking (M10) — the form only
                // shows while none exists and the feature flag is on.
                'can_review' => $review === null
                    && $settings->boolean('reviews.enabled', true)
                    && ($request->user()?->can('review', $booking) ?? false),
            ],
            'review' => $review !== null ? new ReviewResource($review) : null,
            'review_max_photos' => $settings->integer('reviews.max_photos', 4),
            'slot_days' => $canReschedule ? $slots->days() : [],
            'is_favorite_provider' => $booking->provider !== null && $request->user() !== null
                ? $request->user()->hasFavorited($booking->provider)
                : false,
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $booking = $action->byCustomer($booking, $user, $request->validated('reason'));

        $message = (float) $booking->cancellation_fee > 0
            ? __('Booking cancelled. A cancellation fee of :amount applies.', ['amount' => (string) $booking->cancellation_fee])
            : __('Booking cancelled.');

        return back()->with('success', $message);
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking, RescheduleBooking $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $action->handle(
            $booking,
            $user,
            CarbonImmutable::parse((string) $request->validated('scheduled_at'))->utc(),
        );

        return back()->with('success', __('Booking rescheduled.'));
    }

    public function rebook(Request $request, Booking $booking, RebookBooking $action): RedirectResponse
    {
        Gate::authorize('rebook', $booking);

        $added = $action->handle($booking);

        if ($added === 0) {
            return back()->with('error', __('Those services are no longer available.'));
        }

        return redirect()->route('cart.show')->with('success', __('Services added back to your cart.'));
    }
}
