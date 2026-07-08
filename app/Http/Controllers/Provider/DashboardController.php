<?php

namespace App\Http\Controllers\Provider;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderProfileResource;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Approved providers only (provider.approved middleware).
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->providerProfile()->with(['categories', 'blackouts'])->firstOrFail();

        $pendingOffers = DispatchOffer::query()
            ->where('provider_id', $user->id)
            ->where('status', OfferStatus::Offered->value)
            ->whereHas('booking', fn ($query) => $query->where('status', BookingStatus::Searching->value))
            ->count();

        $activeJobs = Booking::query()
            ->where('provider_id', $user->id)
            ->whereIn('status', [
                BookingStatus::Assigned->value,
                BookingStatus::Accepted->value,
                BookingStatus::EnRoute->value,
                BookingStatus::Arrived->value,
                BookingStatus::InProgress->value,
            ])
            ->count();

        return Inertia::render('provider/dashboard', [
            'profile' => new ProviderProfileResource($profile),
            'pending_offers' => $pendingOffers,
            'active_jobs' => $activeJobs,
        ]);
    }
}
