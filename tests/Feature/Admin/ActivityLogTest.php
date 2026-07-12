<?php

use App\Domain\Activity\ActivityLogger;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\PayoutRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EarningsFixtures;
use Tests\Support\SettingsFixtures;

function activityAdmin(): User
{
    return User::factory()->admin()->create();
}

it('logs a manual booking transition with its target status', function () {
    $admin = activityAdmin();
    $booking = EarningsFixtures::booking();

    $this->actingAs($admin)
        ->post(route('admin.bookings.transition', $booking), ['to' => BookingStatus::Completed->value])
        ->assertRedirect();

    $log = ActivityLog::query()->where('action', 'booking.transition')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($booking->id)
        ->and($log->context['to'] ?? null)->toBe('completed');
});

it('does not log a transition the state machine rejected', function () {
    $admin = activityAdmin();
    $booking = EarningsFixtures::booking(); // in_progress — cannot jump to searching

    $this->actingAs($admin)
        ->post(route('admin.bookings.transition', $booking), ['to' => BookingStatus::Searching->value])
        ->assertSessionHasErrors();

    expect(ActivityLog::query()->where('action', 'booking.transition')->count())->toBe(0);
});

it('logs payout decisions with the amount', function () {
    $admin = activityAdmin();
    $provider = User::factory()->provider()->create();

    $payout = PayoutRequest::query()->create([
        'provider_id' => $provider->id,
        'amount' => '250.00',
        'status' => 'requested',
        'method_details' => ['method' => 'upi', 'upi_id' => 'demo@upi'],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.payouts.approve', $payout))
        ->assertRedirect();

    $log = ActivityLog::query()->where('action', 'payout.approve')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($payout->id)
        ->and((float) ($log->context['amount'] ?? 0))->toBe(250.0);
});

it('logs settings saves with keys only — never values', function () {
    $admin = activityAdmin();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'branding'), SettingsFixtures::payload('branding'))
        ->assertRedirect();

    $log = ActivityLog::query()->where('action', 'settings.update')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->context)->toHaveKey('keys')
        ->and($log->context)->not->toHaveKey('app_name')
        ->and(json_encode($log->context))->not->toContain('Acme Services');
});

it('shows logged activity on the admin activity page', function () {
    $admin = activityAdmin();
    $booking = EarningsFixtures::booking();

    $this->actingAs($admin)
        ->post(route('admin.bookings.transition', $booking), ['to' => BookingStatus::Completed->value]);

    $response = $this->actingAs($admin)->get(route('admin.activity.index', ['action' => 'booking.transition']));

    $response->assertOk()->assertInertia(function (AssertableInertia $page) {
        $rows = $page->toArray()['props']['logs']['data'];

        expect($rows)->not->toBeEmpty()
            ->and(array_column($rows, 'action'))->each->toBe('booking.transition');

        return $page->component('admin/activity/index');
    });
});

it('blocks non-admins from the activity log', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.activity.index'))
        ->assertForbidden();
});

it('never updates an activity row', function () {
    $log = app(ActivityLogger::class)->log(activityAdmin(), 'settings.update');

    expect($log)->not->toBeNull()
        ->and($log::UPDATED_AT)->toBeNull();
});
