<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Events\ProviderApprovalChanged;
use App\Models\ProviderProfile;
use Illuminate\Validation\ValidationException;

class ReviewProvider
{
    /**
     * Admin decision on a provider profile. Approval requires a
     * complete profile; going offline on suspension is immediate.
     */
    public function handle(ProviderProfile $profile, ProviderApprovalStatus $to, ?string $note = null): ProviderProfile
    {
        if ($to === ProviderApprovalStatus::Approved && ! $profile->isComplete()) {
            throw ValidationException::withMessages([
                'status' => __('The profile is incomplete — base location, working hours, and at least one category are required before approval.'),
            ]);
        }

        $from = $profile->approval_status;

        $profile->approval_status = $to;
        $profile->approval_note = $note;

        // A provider who loses approval must not stay dispatchable.
        if (! $to->unlocksPanel()) {
            $profile->is_online = false;
        }

        $profile->save();

        if ($from !== $to) {
            ProviderApprovalChanged::dispatch($profile, $from, $to);
        }

        return $profile;
    }
}
