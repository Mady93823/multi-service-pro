<?php

namespace App\Domain\Providers\Enums;

enum ProviderApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * Only approved providers see the working panel; every other
     * status lands on the onboarding screen.
     */
    public function unlocksPanel(): bool
    {
        return $this === self::Approved;
    }
}
