<?php

use App\Domain\Users\Enums\Role;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// M11 in-app realtime — Laravel broadcast notifications land here.
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// M07 live tracking. One authorization guards both the private data channel
// (LocationUpdated / status pushes) and the presence channel (who is watching);
// returning member info authorizes the private channel too. Only the booking's
// customer, its assigned provider, or an admin may subscribe (05-Live-Tracking).
Broadcast::channel('tracking.booking.{booking}', function (User $user, Booking $booking) {
    $allowed = $user->id === $booking->customer_id
        || $user->id === $booking->provider_id
        || $user->hasRole(Role::Admin->value);

    if (! $allowed) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->id === $booking->provider_id ? 'provider' : ($user->id === $booking->customer_id ? 'customer' : 'admin'),
    ];
});
