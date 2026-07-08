<?php

namespace Database\Seeders;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Domain\Providers\Enums\ProviderDocumentType;
use App\Domain\Users\Enums\Role;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProviderSeeder extends Seeder
{
    /**
     * Demo provider states: provider@demo.test approved + online,
     * provider2@demo.test pending in the admin review queue. Idempotent.
     */
    public function run(): void
    {
        $approved = User::query()->where('email', 'provider@demo.test')->first();

        if ($approved === null || $approved->providerProfile()->exists()) {
            return;
        }

        $categoryIds = Category::query()->active()->root()->orderBy('sort_order')->limit(2)->pluck('id');

        $profile = $this->makeProfile($approved, ProviderApprovalStatus::Approved, isOnline: true, bio: 'Certified deep-cleaning and appliance specialist. On the platform since launch.');
        $profile->categories()->sync($categoryIds);
        $this->attachDocuments($profile, ProviderDocumentStatus::Approved, reviewedBy: User::query()->where('email', 'admin@demo.test')->value('id'));

        $profile->blackouts()->create([
            'starts_on' => now()->addDays(20)->toDateString(),
            'ends_on' => now()->addDays(22)->toDateString(),
            'reason' => 'Family visit',
        ]);

        $pendingUser = User::query()->firstOrCreate(
            ['email' => 'provider2@demo.test'],
            [
                'name' => 'Ravi Kumar',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $pendingUser->syncRoles([Role::Provider->value]);

        if (! $pendingUser->providerProfile()->exists()) {
            $pending = $this->makeProfile($pendingUser, ProviderApprovalStatus::Pending, isOnline: false, bio: 'Plumbing and electrical repairs, 8 years of field experience.');
            $pending->categories()->sync($categoryIds->take(1));
            $this->attachDocuments($pending, ProviderDocumentStatus::Pending, reviewedBy: null);
        }
    }

    private function makeProfile(User $user, ProviderApprovalStatus $status, bool $isOnline, string $bio): ProviderProfile
    {
        $profile = new ProviderProfile([
            'bio' => $bio,
            'experience_years' => 8,
            'base_lat' => 12.9716,
            'base_lng' => 77.5946,
            'service_radius_km' => 15,
            'working_hours' => ProviderProfileFactory::defaultWorkingHours(),
        ]);

        $profile->user()->associate($user);
        $profile->approval_status = $status;
        $profile->is_online = $isOnline;
        $profile->save();

        return $profile;
    }

    private function attachDocuments(ProviderProfile $profile, ProviderDocumentStatus $status, ?int $reviewedBy): void
    {
        // 1×1 transparent PNG so the demo "view document" link renders.
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        foreach ([ProviderDocumentType::IdProof, ProviderDocumentType::AddressProof, ProviderDocumentType::Photo] as $type) {
            $path = "provider-documents/{$profile->id}/{$type->value}.png";
            Storage::disk('local')->put($path, $png);

            $profile->documents()->create([
                'type' => $type,
                'file_path' => $path,
                'status' => $status,
                'reviewed_by' => $status === ProviderDocumentStatus::Pending ? null : $reviewedBy,
                'reviewed_at' => $status === ProviderDocumentStatus::Pending ? null : now(),
            ]);
        }
    }
}
