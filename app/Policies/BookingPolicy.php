<?php

namespace App\Policies;

use App\Domain\Users\Enums\Role;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Admins can do everything booking-related (support tooling).
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::Admin->value) ? true : null;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id
            || $booking->provider_id === $user->id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id;
    }

    public function reschedule(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id;
    }

    public function rebook(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id;
    }
}
