<?php

use App\Domain\Comms\MailConfigurator;
use App\Domain\Settings\SettingsRegistry;
use App\Mail\TestEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SettingsFixtures;

/**
 * M23: SMTP lives in settings, not `.env`, and the password is write-only like
 * every other credential here (M08). An install with no SMTP is a supported
 * state, not a broken one.
 */
function smtpPayload(array $overrides = []): array
{
    return SettingsFixtures::payload('mail', array_merge([
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'postmaster',
        'password' => 'hunter2',
        'encryption' => 'tls',
        'from_address' => 'hello@example.com',
        'from_name' => 'UrbanServe',
    ], $overrides));
}

test('mail is unconfigured until a host and a from-address exist', function () {
    $mail = app(MailConfigurator::class);

    expect($mail->isConfigured())->toBeFalse();

    app(SettingsRegistry::class)->set('mail.host', 'smtp.example.com');

    // A host with nowhere to send from is still not a working mail setup.
    expect($mail->isConfigured())->toBeFalse();

    app(SettingsRegistry::class)->set('mail.from_address', 'hello@example.com');

    expect($mail->isConfigured())->toBeTrue();
});

test('saving SMTP settings configures the mailer', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'mail'), smtpPayload())
        ->assertRedirect();

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('hunter2')
        ->and(config('mail.from.address'))->toBe('hello@example.com');
});

test('the SMTP password is write-only: blank keeps it, remove erases it', function () {
    $admin = User::factory()->admin()->create();
    $settings = app(SettingsRegistry::class);

    $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), smtpPayload());

    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'mail'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('values.password_set', true)->missing('values.password'))
        ->assertDontSee('hunter2');

    $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), smtpPayload(['password' => '']));
    expect($settings->string('mail.password'))->toBe('hunter2');

    $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), smtpPayload([
        'password' => '',
        'remove_password' => true,
    ]));
    expect($settings->string('mail.password'))->toBe('');
});

test('a test email cannot be sent before the server is configured', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.mail.test'))
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('the test email goes to the admin who asked for it', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.settings.update', 'mail'), smtpPayload());

    $this->actingAs($admin)
        ->post(route('admin.settings.mail.test'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail): bool => $mail->hasTo($admin->email));
});

test('a customer can neither save mail settings nor send a test', function () {
    Mail::fake();

    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->put(route('admin.settings.update', 'mail'), smtpPayload())->assertForbidden();
    $this->actingAs($customer)->post(route('admin.settings.mail.test'))->assertForbidden();

    Mail::assertNothingSent();
});
