<?php

namespace App\Support;

use App\Domain\Users\Enums\Role;
use App\Models\User;

class RoleRedirect
{
    /**
     * Route name of the dashboard matching the user's highest-privilege role.
     */
    public static function homeRoute(User $user): string
    {
        return match (true) {
            $user->hasRole(Role::Admin->value) => Role::Admin->homeRoute(),
            $user->hasRole(Role::Provider->value) => Role::Provider->homeRoute(),
            default => Role::Customer->homeRoute(),
        };
    }
}
