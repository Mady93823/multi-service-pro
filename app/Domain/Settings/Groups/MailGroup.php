<?php

namespace App\Domain\Settings\Groups;

class MailGroup extends SettingsGroup
{
    /**
     * The SMTP password is write-only, exactly like the gateway secrets (M08):
     * it is never rendered back, blank means "keep it", and remove_* erases it.
     */
    private const SECRETS = [
        'password' => 'mail.password',
    ];

    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return __('Email');
    }

    public function description(): string
    {
        return __('The SMTP server outgoing email is sent through. Until this is filled in, the platform sends no email at all.');
    }

    public function keys(): array
    {
        return [
            'mail.host',
            'mail.port',
            'mail.username',
            'mail.password',
            'mail.encryption',
            'mail.from_address',
            'mail.from_name',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'host' => ['nullable', 'string', 'max:191'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:191'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'from_address' => ['nullable', 'email', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'remove_password' => ['boolean'],
        ];
    }

    public function values(): array
    {
        return [
            'host' => $this->settings->string('mail.host'),
            'port' => $this->settings->integer('mail.port', 587),
            'username' => $this->settings->string('mail.username'),
            'encryption' => $this->settings->string('mail.encryption', 'tls'),
            'from_address' => $this->settings->string('mail.from_address'),
            'from_name' => $this->settings->string('mail.from_name'),
            'password_set' => $this->settings->string('mail.password') !== '',
            // Drives the "email is off" warning on the screen.
            'configured' => $this->settings->string('mail.host') !== ''
                && $this->settings->string('mail.from_address') !== '',
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('mail.host', $data['host'] ?? null);
        $this->settings->set('mail.port', $data['port']);
        $this->settings->set('mail.username', $data['username'] ?? null);
        $this->settings->set('mail.encryption', $data['encryption']);
        $this->settings->set('mail.from_address', $data['from_address'] ?? null);
        $this->settings->set('mail.from_name', $data['from_name'] ?? null);

        foreach (self::SECRETS as $field => $settingKey) {
            $submitted = $data[$field] ?? null;

            if ($this->toggle($data, 'remove_'.$field)) {
                $this->settings->set($settingKey, null);
            } elseif (is_string($submitted) && $submitted !== '') {
                $this->settings->set($settingKey, $submitted);
            }
        }
    }
}
