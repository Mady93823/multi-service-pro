<?php

namespace App\Domain\Settings\Groups;

class SmsGroup extends SettingsGroup
{
    /** Write-only, like every other API credential in this app (M08). */
    private const SECRETS = [
        'msg91_auth_key' => 'sms.msg91_auth_key',
        'twilio_token' => 'sms.twilio_token',
    ];

    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return __('SMS');
    }

    public function description(): string
    {
        return __('The gateway text messages are sent through. Each message costs you money, so every SMS switch on the notification matrix starts off.');
    }

    public function keys(): array
    {
        return [
            'sms.gateway',
            'sms.msg91_auth_key',
            'sms.msg91_sender',
            'sms.msg91_route',
            'sms.twilio_sid',
            'sms.twilio_token',
            'sms.twilio_from',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'gateway' => ['required', 'string', 'in:none,msg91,twilio'],
            'msg91_auth_key' => ['nullable', 'string', 'max:191'],
            'msg91_sender' => ['nullable', 'string', 'max:20'],
            'msg91_route' => ['nullable', 'string', 'max:5'],
            'twilio_sid' => ['nullable', 'string', 'max:191'],
            'twilio_token' => ['nullable', 'string', 'max:191'],
            'twilio_from' => ['nullable', 'string', 'max:20'],
            'remove_msg91_auth_key' => ['boolean'],
            'remove_twilio_token' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $values = [
            'gateway' => $this->settings->string('sms.gateway', 'none'),
            'msg91_sender' => $this->settings->string('sms.msg91_sender'),
            'msg91_route' => $this->settings->string('sms.msg91_route', '4'),
            'twilio_sid' => $this->settings->string('sms.twilio_sid'),
            'twilio_from' => $this->settings->string('sms.twilio_from'),
        ];

        foreach (self::SECRETS as $field => $settingKey) {
            $values[$field.'_set'] = $this->settings->string($settingKey) !== '';
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('sms.gateway', $data['gateway']);
        $this->settings->set('sms.msg91_sender', $data['msg91_sender'] ?? null);
        $this->settings->set('sms.msg91_route', $data['msg91_route'] ?? '4');
        $this->settings->set('sms.twilio_sid', $data['twilio_sid'] ?? null);
        $this->settings->set('sms.twilio_from', $data['twilio_from'] ?? null);

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
