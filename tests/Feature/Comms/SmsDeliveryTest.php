<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\NotificationPreferences;
use App\Domain\Comms\SmsManager;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\NotificationPreference;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\BookingStatusNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SettingsFixtures;

/**
 * M23: SMS is a paid, third-party, frequently-down channel. Two invariants:
 * an unconfigured gateway is inert, and a failing one never takes the booking
 * down with it (M07's rule for a dead Reverb, applied to a dead SMS provider).
 */
function enableMsg91(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('sms.gateway', 'msg91');
    $settings->set('sms.msg91_auth_key', 'auth-key');
    $settings->set('sms.msg91_sender', 'URBAN');

    NotificationPreference::query()->create([
        'user_id' => null,
        'event_key' => NotificationEvent::BookingStatus->value,
        'channel' => NotificationChannel::Sms->value,
        'is_enabled' => true,
    ]);

    app(NotificationPreferences::class)->flush();
}

function notifySms(User $customer): void
{
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);

    $customer->notify(new BookingStatusNotification($booking, BookingStatus::Completed));
}

test('a live gateway sends the message and records it', function () {
    Http::fake(['api.msg91.com/*' => Http::response('3511xxxxx', 200)]);

    $customer = User::factory()->customer()->create(['phone' => '9876543210']);
    enableMsg91();

    notifySms($customer);

    $log = SmsLog::query()->where('user_id', $customer->id)->sole();

    expect($log->status)->toBe(SmsLog::STATUS_SENT)
        ->and($log->gateway)->toBe('msg91')
        ->and($log->event_key)->toBe(NotificationEvent::BookingStatus->value)
        ->and($log->body)->toContain('Service complete');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'msg91.com'));
});

test('a dead gateway is logged as failed and never fails the notification', function () {
    Http::fake(['api.msg91.com/*' => Http::response('down', 500)]);

    $customer = User::factory()->customer()->create(['phone' => '9876543210']);
    enableMsg91();

    // The whole point: this call does not throw.
    notifySms($customer);

    $log = SmsLog::query()->where('user_id', $customer->id)->sole();

    expect($log->status)->toBe(SmsLog::STATUS_FAILED)
        ->and($log->response)->toHaveKey('error');

    // And the in-app notification still landed.
    expect($customer->notifications()->count())->toBe(1);
});

test('with no gateway configured nothing is sent and nothing is logged', function () {
    Http::fake();

    $customer = User::factory()->customer()->create(['phone' => '9876543210']);

    notifySms($customer);

    expect(SmsLog::query()->where('user_id', $customer->id)->exists())->toBeFalse()
        ->and(app(SmsManager::class)->active())->toBeNull();

    Http::assertNothingSent();
});

test('a half-configured gateway counts as no gateway', function () {
    $settings = app(SettingsRegistry::class);
    // Selected, but no auth key: an operator who saved half the form.
    $settings->set('sms.gateway', 'msg91');
    $settings->set('sms.msg91_sender', 'URBAN');

    expect(app(SmsManager::class)->active())->toBeNull();
});

test('twilio is the same contract with different plumbing', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    $settings = app(SettingsRegistry::class);
    $settings->set('sms.gateway', 'twilio');
    $settings->set('sms.twilio_sid', 'AC123');
    $settings->set('sms.twilio_token', 'token');
    $settings->set('sms.twilio_from', '+15551234567');

    $gateway = app(SmsManager::class)->active();

    expect($gateway?->key())->toBe('twilio');

    $result = $gateway?->send('+919876543210', 'Hello');

    expect($result?->sent)->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.twilio.com'));
});

test('the SMS credentials are write-only on the settings screen', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'sms'), SettingsFixtures::payload('sms', [
            'gateway' => 'msg91',
            'msg91_auth_key' => 'super-secret',
            'msg91_sender' => 'URBAN',
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->string('sms.msg91_auth_key'))->toBe('super-secret');

    // Inertia serializes every prop into the page HTML — the value must never
    // be a prop, only the fact that one is stored (M08).
    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'sms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('values.msg91_auth_key_set', true)
            ->missing('values.msg91_auth_key'))
        ->assertDontSee('super-secret');

    // Blank keeps it; remove_* erases it.
    $this->actingAs($admin)->put(route('admin.settings.update', 'sms'), SettingsFixtures::payload('sms', [
        'gateway' => 'msg91',
        'msg91_sender' => 'URBAN',
    ]));

    expect(app(SettingsRegistry::class)->string('sms.msg91_auth_key'))->toBe('super-secret');

    $this->actingAs($admin)->put(route('admin.settings.update', 'sms'), SettingsFixtures::payload('sms', [
        'gateway' => 'none',
        'remove_msg91_auth_key' => true,
    ]));

    expect(app(SettingsRegistry::class)->string('sms.msg91_auth_key'))->toBe('');
});

test('an SMS-less notification still reaches everyone in-app', function () {
    Notification::fake();

    $customer = User::factory()->customer()->create();

    notifySms($customer);

    Notification::assertSentTo($customer, BookingStatusNotification::class);
});
