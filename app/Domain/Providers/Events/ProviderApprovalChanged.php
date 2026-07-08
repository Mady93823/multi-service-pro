<?php

namespace App\Domain\Providers\Events;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Models\ProviderProfile;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired on every admin review decision. M11 notifications listen here
 * to tell the provider they were approved / rejected / suspended.
 */
class ProviderApprovalChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ProviderProfile $profile,
        public readonly ProviderApprovalStatus $from,
        public readonly ProviderApprovalStatus $to,
    ) {}
}
