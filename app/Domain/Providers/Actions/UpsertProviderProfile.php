<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertProviderProfile
{
    /**
     * Create or update the provider's onboarding profile.
     *
     * @param  array{bio?: string|null, experience_years?: int|null, base_lat: float, base_lng: float, service_radius_km: int, working_hours: array<string, array{off: bool, start?: string, end?: string}>, category_ids: list<int>}  $data
     */
    public function handle(User $user, array $data): ProviderProfile
    {
        return DB::transaction(function () use ($user, $data): ProviderProfile {
            /** @var ProviderProfile $profile */
            $profile = $user->providerProfile()->firstOrNew();

            $profile->fill([
                'bio' => $data['bio'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
                'base_lat' => $data['base_lat'],
                'base_lng' => $data['base_lng'],
                'service_radius_km' => $data['service_radius_km'],
                'working_hours' => $data['working_hours'],
            ]);

            // Editing after a rejection is a resubmission — back into the
            // admin review queue. Approved/suspended statuses stay put.
            if ($profile->approval_status === ProviderApprovalStatus::Rejected) {
                $profile->approval_status = ProviderApprovalStatus::Pending;
                $profile->approval_note = null;
            }

            $profile->user()->associate($user);
            $profile->save();
            $profile->categories()->sync($data['category_ids']);

            return $profile;
        });
    }
}
