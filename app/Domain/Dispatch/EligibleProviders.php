<?php

namespace App\Domain\Dispatch;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\DispatchOffer;
use App\Models\ProviderProfile;
use Illuminate\Support\Collection;

/**
 * Finds providers who may take a booking: approved + online, serving one of the
 * booking's service categories (or an ancestor), within their own service
 * radius of the address, not on a blackout that day, not already busy on an
 * overlapping job, and not already offered this booking. Geo maths run in PHP
 * (Haversine) rather than in the DB — same portability call as zone
 * point-in-polygon (ADR D12).
 */
class EligibleProviders
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Booking statuses that occupy a provider's time slot.
     *
     * @var list<BookingStatus>
     */
    private const BUSY_STATUSES = [
        BookingStatus::Assigned,
        BookingStatus::Accepted,
        BookingStatus::EnRoute,
        BookingStatus::Arrived,
        BookingStatus::InProgress,
    ];

    /**
     * @return Collection<int, EligibleProvider>
     */
    public function forBooking(Booking $booking): Collection
    {
        $categoryIds = $this->requiredCategoryIds($booking);

        if ($categoryIds === []) {
            return collect();
        }

        $lat = (float) ($booking->address_snapshot['lat'] ?? 0.0);
        $lng = (float) ($booking->address_snapshot['lng'] ?? 0.0);

        $excluded = array_unique([
            ...$this->alreadyOfferedProviderIds($booking),
            ...$this->busyProviderIds($booking),
        ]);

        $profiles = ProviderProfile::query()
            ->where('approval_status', ProviderApprovalStatus::Approved->value)
            ->where('is_online', true)
            ->whereNotNull('base_lat')
            ->whereNotNull('base_lng')
            ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $categoryIds))
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('user_id', $excluded))
            ->with(['user:id,name', 'blackouts'])
            ->get();

        return $profiles
            ->map(fn (ProviderProfile $profile): EligibleProvider => new EligibleProvider(
                $profile,
                $this->distanceKm($lat, $lng, (float) $profile->base_lat, (float) $profile->base_lng),
            ))
            ->filter(fn (EligibleProvider $candidate): bool => $candidate->distanceKm <= (float) $candidate->profile->service_radius_km)
            ->reject(fn (EligibleProvider $candidate): bool => $this->onBlackout($candidate->profile, $booking))
            ->sortBy(fn (EligibleProvider $candidate): float => $candidate->distanceKm)
            ->values();
    }

    /**
     * Each booking service's category plus its ancestor chain, so a provider
     * who registered a parent category still matches a child-category service.
     *
     * @return list<int>
     */
    private function requiredCategoryIds(Booking $booking): array
    {
        $serviceCategoryIds = $booking->items()
            ->with('service:id,category_id')
            ->get()
            ->map(fn ($item): ?int => $item->service?->category_id)
            ->filter()
            ->unique()
            ->all();

        if ($serviceCategoryIds === []) {
            return [];
        }

        /** @var array<int, int|null> $parentOf */
        $parentOf = Category::query()->pluck('parent_id', 'id')->all();

        $result = [];
        foreach ($serviceCategoryIds as $categoryId) {
            $current = $categoryId;
            while ($current !== null && ! in_array($current, $result, true)) {
                $result[] = $current;
                $current = $parentOf[$current] ?? null;
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function alreadyOfferedProviderIds(Booking $booking): array
    {
        return DispatchOffer::query()
            ->where('booking_id', $booking->id)
            ->pluck('provider_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Providers already committed to an overlapping job at this slot.
     *
     * @return list<int>
     */
    private function busyProviderIds(Booking $booking): array
    {
        return Booking::query()
            ->whereNotNull('provider_id')
            ->where('id', '!=', $booking->id)
            ->whereIn('status', array_map(fn (BookingStatus $status): string => $status->value, self::BUSY_STATUSES))
            ->where('scheduled_at', '<', $booking->slot_end_at)
            ->where('slot_end_at', '>', $booking->scheduled_at)
            ->pluck('provider_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function onBlackout(ProviderProfile $profile, Booking $booking): bool
    {
        return $profile->blackouts
            ->contains(fn ($blackout): bool => $blackout->covers($booking->scheduled_at));
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }
}
