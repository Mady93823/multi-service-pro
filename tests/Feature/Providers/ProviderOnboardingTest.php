<?php

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function onboardingProvider(): User
{
    return User::factory()->provider()->create();
}

/**
 * @return array<string, mixed>
 */
function onboardingPayload(int $categoryId): array
{
    $working = ['off' => false, 'start' => '09:00', 'end' => '18:00'];

    return [
        'bio' => 'Experienced appliance technician.',
        'experience_years' => 5,
        'base_lat' => 12.9716,
        'base_lng' => 77.5946,
        'service_radius_km' => 12,
        'working_hours' => [
            'mon' => $working,
            'tue' => $working,
            'wed' => $working,
            'thu' => $working,
            'fri' => $working,
            'sat' => $working,
            'sun' => ['off' => true],
        ],
        'category_ids' => [$categoryId],
    ];
}

test('an unapproved provider is bounced from the panel to onboarding', function () {
    $provider = onboardingProvider();

    $this->actingAs($provider)
        ->get(route('provider.dashboard'))
        ->assertRedirect(route('provider.onboarding'));
});

test('an approved provider reaches the dashboard', function () {
    $provider = onboardingProvider();
    ProviderProfile::factory()->approved()->for($provider)->create();

    $this->actingAs($provider)
        ->get(route('provider.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('provider/dashboard')
            ->where('profile.approval_status', 'approved'));
});

test('saving the profile creates it and syncs categories', function () {
    $provider = onboardingProvider();
    $category = Category::factory()->create(['is_active' => true]);

    $this->actingAs($provider)
        ->put(route('provider.profile.update'), onboardingPayload($category->id))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $profile = $provider->providerProfile()->first();

    expect($profile)->not->toBeNull()
        ->and($profile->service_radius_km)->toBe(12)
        ->and($profile->approval_status)->toBe(ProviderApprovalStatus::Pending)
        ->and($profile->working_hours['sun']['off'])->toBeTrue()
        ->and($profile->categories()->pluck('categories.id')->all())->toBe([$category->id]);
});

test('working hours must end after they start', function () {
    $provider = onboardingProvider();
    $category = Category::factory()->create(['is_active' => true]);

    $payload = onboardingPayload($category->id);
    $payload['working_hours']['mon'] = ['off' => false, 'start' => '18:00', 'end' => '09:00'];

    $this->actingAs($provider)
        ->put(route('provider.profile.update'), $payload)
        ->assertSessionHasErrors(['working_hours.mon.end']);
});

test('a document upload lands on the private disk pending review', function () {
    Storage::fake('local');
    $provider = onboardingProvider();

    $this->actingAs($provider)
        ->post(route('provider.documents.store'), [
            'type' => 'id_proof',
            'file' => UploadedFile::fake()->image('aadhaar.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $document = $provider->providerProfile()->firstOrFail()->documents()->firstOrFail();

    expect($document->status)->toBe(ProviderDocumentStatus::Pending);
    Storage::disk('local')->assertExists($document->file_path);
});

test('re-uploading a document type replaces the previous file', function () {
    Storage::fake('local');
    $provider = onboardingProvider();

    $this->actingAs($provider)->post(route('provider.documents.store'), [
        'type' => 'id_proof',
        'file' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $profile = $provider->providerProfile()->firstOrFail();
    $firstPath = $profile->documents()->firstOrFail()->file_path;

    $this->actingAs($provider)->post(route('provider.documents.store'), [
        'type' => 'id_proof',
        'file' => UploadedFile::fake()->image('second.jpg'),
    ]);

    expect($profile->documents()->count())->toBe(1);
    Storage::disk('local')->assertMissing($firstPath);
});

test('editing a rejected profile resubmits it for review', function () {
    $provider = onboardingProvider();
    ProviderProfile::factory()->rejected()->for($provider)->create();
    $category = Category::factory()->create(['is_active' => true]);

    $this->actingAs($provider)
        ->put(route('provider.profile.update'), onboardingPayload($category->id))
        ->assertSessionHasNoErrors();

    $profile = $provider->providerProfile()->firstOrFail();

    expect($profile->approval_status)->toBe(ProviderApprovalStatus::Pending)
        ->and($profile->approval_note)->toBeNull();
});

test('KYC files are served to the owner and admins only', function () {
    Storage::fake('local');
    $provider = onboardingProvider();

    $this->actingAs($provider)->post(route('provider.documents.store'), [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('me.png'),
    ]);

    $document = $provider->providerProfile()->firstOrFail()->documents()->firstOrFail();

    $this->actingAs($provider)->get(route('provider-documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('provider-documents.show', $document))->assertOk();
    $this->actingAs(User::factory()->provider()->create())->get(route('provider-documents.show', $document))->assertForbidden();
});
