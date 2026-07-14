<?php

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\ActivityLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;

/**
 * M23 push composer: an announcement is an ordinary notification, so it inherits
 * the channels, the preferences and the in-app feed. No second delivery path.
 */
test('an announcement reaches the chosen segment and nobody else', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $provider = User::factory()->provider()->create();

    $this->actingAs($admin)
        ->post(route('admin.notifications.announce'), [
            'segment' => 'customers',
            'title' => 'Diwali offer',
            'message' => 'Twenty percent off this week.',
            'url' => 'https://example.com/offer',
        ])
        ->assertRedirect();

    Notification::assertSentTo($customer, AnnouncementNotification::class);
    Notification::assertNotSentTo($provider, AnnouncementNotification::class);
    // Admins are staff, not an audience.
    Notification::assertNotSentTo($admin, AnnouncementNotification::class);
});

test('a blocked account is not announced to', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $blocked = User::factory()->customer()->create(['is_active' => false]);

    $this->actingAs($admin)->post(route('admin.notifications.announce'), [
        'segment' => 'all',
        'title' => 'Hello',
        'message' => 'Everyone.',
    ]);

    Notification::assertNotSentTo($blocked, AnnouncementNotification::class);
});

test('the announcement lands in the in-app feed with its link', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($admin)->post(route('admin.notifications.announce'), [
        'segment' => 'customers',
        'title' => 'Scheduled maintenance',
        'message' => 'We are upgrading on Sunday.',
        'url' => 'https://example.com/status',
    ]);

    $notification = $customer->notifications()->sole();

    expect($notification->data['type'])->toBe('announcement')
        ->and($notification->data['title'])->toBe('Scheduled maintenance')
        ->and($notification->data['url'])->toBe('https://example.com/status');
});

test('a javascript link is refused — an href is a script sink', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.notifications.announce'), [
            'segment' => 'all',
            'title' => 'Hi',
            'message' => 'There.',
            'url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('url');
});

test('sending an announcement is audited', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->customer()->create();

    $this->actingAs($admin)->post(route('admin.notifications.announce'), [
        'segment' => 'customers',
        'title' => 'Hello',
        'message' => 'World.',
    ]);

    expect(ActivityLog::query()->where('action', 'notifications.announce')->exists())->toBeTrue();
});

test('the matrix screen saves the platform defaults, and only an admin may open it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/notifications/index')->has('matrix'));

    $this->actingAs($admin)
        ->put(route('admin.notifications.update'), [
            'preferences' => [
                ['event' => NotificationEvent::BookingStatus->value, 'channel' => NotificationChannel::Mail->value, 'enabled' => false],
            ],
        ])
        ->assertRedirect();

    expect(NotificationPreference::query()->platform()->where('event_key', 'booking_status')->sole()->is_enabled)->toBeFalse();

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.notifications.index'))
        ->assertForbidden();
});

test('a payload naming an event that does not exist is refused, not stored', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.notifications.update'), [
            'preferences' => [
                ['event' => 'made_up_event', 'channel' => 'mail', 'enabled' => true],
            ],
        ])
        ->assertSessionHasErrors('preferences.0.event');

    expect(NotificationPreference::query()->count())->toBe(0);
});

test('a user saves their own opt-outs and they beat the platform default', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/notifications'));

    $this->actingAs($customer)
        ->put(route('notifications.update'), [
            'preferences' => [
                ['event' => NotificationEvent::BookingStatus->value, 'channel' => NotificationChannel::Mail->value, 'enabled' => false],
            ],
        ])
        ->assertRedirect();

    $preference = NotificationPreference::query()->where('user_id', $customer->id)->sole();

    expect($preference->is_enabled)->toBeFalse()
        ->and($preference->channel)->toBe('mail');
});

test('a user cannot write another user\'s preferences', function () {
    $customer = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    // There is no user id in the payload to forge — the route takes the session's.
    $this->actingAs($customer)->put(route('notifications.update'), [
        'user_id' => $other->id,
        'preferences' => [
            ['event' => NotificationEvent::BookingStatus->value, 'channel' => NotificationChannel::Mail->value, 'enabled' => false],
        ],
    ]);

    expect(NotificationPreference::query()->where('user_id', $other->id)->exists())->toBeFalse()
        ->and(NotificationPreference::query()->where('user_id', $customer->id)->exists())->toBeTrue();
});
