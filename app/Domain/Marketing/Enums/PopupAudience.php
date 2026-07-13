<?php

namespace App\Domain\Marketing\Enums;

use App\Models\User;

enum PopupAudience: string
{
    case Everyone = 'everyone';
    case Guests = 'guests';
    case Customers = 'customers';
    case Providers = 'providers';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('Everyone'),
            self::Guests => __('Signed-out visitors'),
            self::Customers => __('Customers'),
            self::Providers => __('Providers'),
        };
    }

    public function allows(?User $user): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Guests => $user === null,
            self::Customers => $user !== null && $user->hasRole('customer'),
            self::Providers => $user !== null && $user->hasRole('provider'),
        };
    }
}
