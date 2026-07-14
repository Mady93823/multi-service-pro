<?php

namespace App\Domain\Comms;

use App\Domain\Settings\SettingsRegistry;

/**
 * SMTP lives in settings, not `.env` (M23).
 *
 * A CodeCanyon buyer configures mail from the browser, so the credentials are
 * settings rows (the password write-only, M08's rule) and this pushes them onto
 * Laravel's mailer at boot. Nothing is forced: with no host and no from-address
 * the mailer keeps whatever `.env` said, and `isConfigured()` is false — which
 * is what keeps the `mail` channel out of every notification's via() (D14's
 * rule, borrowed from FCM). The app must install and run with no mail at all.
 */
class MailConfigurator
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->string('mail.host') !== ''
            && $this->settings->string('mail.from_address') !== '';
    }

    public function apply(): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $encryption = $this->settings->string('mail.encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $this->settings->string('mail.host'),
            'mail.mailers.smtp.port' => $this->settings->integer('mail.port', 587),
            'mail.mailers.smtp.username' => $this->nullable('mail.username'),
            'mail.mailers.smtp.password' => $this->nullable('mail.password'),
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.from.address' => $this->settings->string('mail.from_address'),
            'mail.from.name' => $this->fromName(),
        ]);
    }

    public function fromName(): string
    {
        $name = $this->settings->string('mail.from_name');

        return $name !== '' ? $name : $this->settings->string('branding.app_name', (string) config('app.name'));
    }

    private function nullable(string $key): ?string
    {
        $value = $this->settings->string($key);

        return $value === '' ? null : $value;
    }
}
