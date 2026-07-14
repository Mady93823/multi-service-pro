<?php

namespace App\Domain\Settings\Groups;

class RecaptchaGroup extends SettingsGroup
{
    private const SECRETS = [
        'secret_key' => 'recaptcha.secret_key',
    ];

    /** The forms a token can be demanded on. */
    public const FORMS = ['register', 'login', 'contact', 'ticket'];

    public function key(): string
    {
        return 'recaptcha';
    }

    public function label(): string
    {
        return __('reCaptcha');
    }

    public function description(): string
    {
        return __('Google reCaptcha v3 on the forms strangers can reach. Off by default, and inert until both keys are saved — a missing key never blocks a signup.');
    }

    public function keys(): array
    {
        return [
            'recaptcha.site_key',
            'recaptcha.secret_key',
            'recaptcha.on_register',
            'recaptcha.on_login',
            'recaptcha.on_contact',
            'recaptcha.on_ticket',
        ];
    }

    public function rules(array $input): array
    {
        return [
            // The site key is public by design — it ships to the browser.
            'site_key' => ['nullable', 'string', 'max:191'],
            'secret_key' => ['nullable', 'string', 'max:191'],
            'remove_secret_key' => ['boolean'],
            'on_register' => ['boolean'],
            'on_login' => ['boolean'],
            'on_contact' => ['boolean'],
            'on_ticket' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $values = [
            'site_key' => $this->settings->string('recaptcha.site_key'),
            'secret_key_set' => $this->settings->string('recaptcha.secret_key') !== '',
        ];

        foreach (self::FORMS as $form) {
            $values['on_'.$form] = $this->settings->boolean('recaptcha.on_'.$form);
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('recaptcha.site_key', $data['site_key'] ?? null);

        foreach (self::FORMS as $form) {
            $this->settings->set('recaptcha.on_'.$form, $this->toggle($data, 'on_'.$form));
        }

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
