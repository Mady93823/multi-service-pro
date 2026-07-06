<?php

namespace App\Domain\Users\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Provider = 'provider';
    case Admin = 'admin';

    /**
     * Route name of the role's post-login landing screen.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Provider => 'provider.dashboard',
            self::Customer => 'dashboard',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
