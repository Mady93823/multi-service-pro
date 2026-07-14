<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\NotificationChannels;
use App\Domain\Comms\NotificationPreferences;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\BookingStatusNotification;
use App\Notifications\SmsChannel;

/**
 * M23: one resolver decides every notification's via(). Two rules it must never
 * break — a channel whose provider is not configured is never used (D14: the
 * app must run with no mail, no SMS and no Firebase), and a user's opt-out beats
 * the admin's default.
 */
function configureMail(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('mail.host', 'smtp.example.com');
    $settings->set('mail.from_address', 'hello@example.com');
}

function configureSms(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('sms.gateway', 'msg91');
    $settings->set('sms.msg91_auth_key', 'key');
    $settings->set('sms.msg91_sender', 'URBAN');
}

function channelsFor(User $user): array
{
    $booking = Booking::factory()->create(['customer_id' => $user->id]);

    return (new BookingStatusNotification($booking, BookingStatus::Completed))->via($user);
}

beforeEach(function () {
    app(NotificationPreferences::class)->flush();
});

test('a fresh install sends in-app only — no mail, no SMS, no push', function () {
    $user = User::factory()->customer()->create(['phone' => '9876543210']);

    expect(channelsFor($user))->toBe(['database', 'broadcast']);
});

test('configuring SMTP is what puts mail on the wire', function () {
    $user = User::factory()->customer()->create();

    configureMail();

    expect(channelsFor($user))->toContain('mail');
});

test('an admin can switch a channel off for everyone', function () {
    $user = User::factory()->customer()->create();
    configureMail();

    NotificationPreference::factory()->create([
        'user_id' => null,
        'event_key' => NotificationEvent::BookingStatus->value,
        'channel' => NotificationChannel::Mail->value,
        'is_enabled' => false,
    ]);
    app(NotificationPreferences::class)->flush();

    expect(channelsFor($user))->not->toContain('mail');
});

test('a user opt-out beats the platform default', function () {
    $user = User::factory()->customer()->create();
    configureMail();

    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'event_key' => NotificationEvent::BookingStatus->value,
        'channel' => NotificationChannel::Mail->value,
        'is_enabled' => false,
    ]);

    expect(channelsFor($user))->not->toContain('mail');
});

test('a preference cannot conjure a channel the install has not configured', function () {
    $user = User::factory()->customer()->create(['phone' => '9876543210']);

    // Mail on, everywhere — but there is no SMTP server, so nothing may queue.
    NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'event_key' => NotificationEvent::BookingStatus->value,
        'channel' => NotificationChannel::Mail->value,
        'is_enabled' => true,
    ]);
    app(NotificationPreferences::class)->flush();

    expect(channelsFor($user))->toBe(['database', 'broadcast']);
});

test('SMS rides only when a gateway is live, the switch is on and the user has a phone', function () {
    $user = User::factory()->customer()->create(['phone' => '9876543210']);
    configureSms();

    NotificationPreference::factory()->create([
        'user_id' => null,
        'event_key' => NotificationEvent::BookingStatus->value,
        'channel' => NotificationChannel::Sms->value,
        'is_enabled' => true,
    ]);
    app(NotificationPreferences::class)->flush();

    expect(channelsFor($user))->toContain(SmsChannel::class);

    // Same install, same switch, no phone number: nothing to send to.
    $noPhone = User::factory()->customer()->create(['phone' => null]);

    expect(channelsFor($noPhone))->not->toContain(SmsChannel::class);
});

test('SMS is off by shipped default — it costs the operator money', function () {
    $user = User::factory()->customer()->create(['phone' => '9876543210']);
    configureSms();

    expect(channelsFor($user))->not->toContain(SmsChannel::class);
});

test('the in-app channels can never be switched off', function () {
    $user = User::factory()->customer()->create();

    // There is no `database` or `broadcast` case to even ask for.
    expect(array_column(NotificationChannel::cases(), 'value'))
        ->toBe(['mail', 'sms', 'fcm'])
        ->and(channelsFor($user))->toContain('database')
        ->and(channelsFor($user))->toContain('broadcast');
});

test('a job offer does not email by default, but a booking update does', function () {
    configureMail();

    $preferences = app(NotificationPreferences::class);

    expect($preferences->enabled(NotificationEvent::JobOffer, NotificationChannel::Mail))->toBeFalse()
        ->and($preferences->enabled(NotificationEvent::BookingStatus, NotificationChannel::Mail))->toBeTrue();
});

test('the resolver is the only thing deciding via(), so an unknown notifiable is safe', function () {
    configureMail();

    $channels = app(NotificationChannels::class)->for(NotificationEvent::BookingStatus, new stdClass);

    // No user, no email address, no phone — the in-app pair and nothing else.
    expect($channels)->toBe(['database', 'broadcast']);
});
