<?php

namespace App\Domain\Cms\Enums;

use App\Models\User;

enum MenuVisibility: string
{
    case Everyone = 'everyone';
    case Guests = 'guests';
    case SignedIn = 'signed_in';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('Everyone'),
            self::Guests => __('Signed-out visitors'),
            self::SignedIn => __('Signed-in users'),
        };
    }

    public function allows(?User $user): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Guests => $user === null,
            self::SignedIn => $user !== null,
        };
    }
}
