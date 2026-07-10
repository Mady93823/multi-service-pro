<?php

namespace App\Policies;

use App\Domain\Users\Enums\Role;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Visibility of a review (and its photos). The storefront shows reviews
     * to guests, so the user is nullable — before() never runs for guests,
     * which is why the admin check sits here instead.
     */
    public function view(?User $user, Review $review): bool
    {
        if (! $review->is_hidden) {
            return true;
        }

        return $user !== null
            && ($user->hasRole(Role::Admin->value) || $review->customer_id === $user->id);
    }
}
