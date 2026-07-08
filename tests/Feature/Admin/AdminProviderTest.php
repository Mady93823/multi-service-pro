<?php

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use App\Domain\Providers\Enums\ProviderDocumentStatus;
use App\Domain\Providers\Enums\ProviderDocumentType;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;

function providerAdmin(): User
{
    return User::factory()->admin()->create();
}

function reviewableProfile(bool $complete = true): ProviderProfile
{
    $profile = ProviderProfile::factory()
        ->for(User::factory()->provider())
        ->create();

    if ($complete) {
        $profile->categories()->sync([Category::factory()->create(['is_active' => true])->id]);
    }

    return $profile;
}

test('non-admins are blocked from the provider panel', function () {
    $profile = reviewableProfile();

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.providers.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->customer()->create())
        ->post(route('admin.providers.review', $profile->user_id), ['status' => 'approved'])
        ->assertForbidden();
});

test('the provider list filters by approval status', function () {
    reviewableProfile();

    $this->actingAs(providerAdmin())
        ->get(route('admin.providers.index', ['status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/providers/index')
            ->where('filters.status', 'pending'));
});

test('approving an incomplete profile is blocked', function () {
    $profile = reviewableProfile(complete: false);
    $profile->categories()->detach();

    $this->actingAs(providerAdmin())
        ->post(route('admin.providers.review', $profile->user_id), ['status' => 'approved'])
        ->assertSessionHasErrors(['status']);

    expect($profile->fresh()->approval_status)->toBe(ProviderApprovalStatus::Pending);
});

test('approving a complete profile unlocks the provider panel', function () {
    $profile = reviewableProfile();

    $this->actingAs(providerAdmin())
        ->post(route('admin.providers.review', $profile->user_id), ['status' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($profile->fresh()->approval_status)->toBe(ProviderApprovalStatus::Approved);

    $this->actingAs($profile->user)
        ->get(route('provider.dashboard'))
        ->assertOk();
});

test('rejecting requires a note and stores it', function () {
    $profile = reviewableProfile();

    $this->actingAs(providerAdmin())
        ->post(route('admin.providers.review', $profile->user_id), ['status' => 'rejected'])
        ->assertSessionHasErrors(['note']);

    $this->actingAs(providerAdmin())
        ->post(route('admin.providers.review', $profile->user_id), [
            'status' => 'rejected',
            'note' => 'ID document is unreadable.',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $profile->fresh();
    expect($fresh->approval_status)->toBe(ProviderApprovalStatus::Rejected)
        ->and($fresh->approval_note)->toBe('ID document is unreadable.');
});

test('suspending an online provider forces them offline', function () {
    $profile = reviewableProfile();
    $profile->approval_status = ProviderApprovalStatus::Approved;
    $profile->is_online = true;
    $profile->save();

    $this->actingAs(providerAdmin())
        ->post(route('admin.providers.review', $profile->user_id), [
            'status' => 'suspended',
            'note' => 'Repeated customer complaints.',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $profile->fresh();
    expect($fresh->approval_status)->toBe(ProviderApprovalStatus::Suspended)
        ->and($fresh->is_online)->toBeFalse();
});

test('an admin can approve and reject documents', function () {
    $profile = reviewableProfile();
    $document = $profile->documents()->create([
        'type' => ProviderDocumentType::IdProof,
        'file_path' => 'provider-documents/test/id.png',
        'status' => ProviderDocumentStatus::Pending,
    ]);

    $this->actingAs(providerAdmin())
        ->post(route('admin.provider-documents.review', $document), ['status' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe(ProviderDocumentStatus::Approved);

    $this->actingAs(providerAdmin())
        ->post(route('admin.provider-documents.review', $document), ['status' => 'rejected'])
        ->assertSessionHasErrors(['reject_reason']);

    $this->actingAs(providerAdmin())
        ->post(route('admin.provider-documents.review', $document), [
            'status' => 'rejected',
            'reject_reason' => 'Photo is blurry.',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ProviderDocumentStatus::Rejected)
        ->and($fresh->reject_reason)->toBe('Photo is blurry.')
        ->and($fresh->reviewed_by)->not->toBeNull();
});
