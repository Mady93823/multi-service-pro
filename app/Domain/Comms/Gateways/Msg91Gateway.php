<?php

namespace App\Domain\Comms\Gateways;

use App\Domain\Comms\Contracts\SmsGateway;
use App\Domain\Comms\SmsResult;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * MSG91 over its plain HTTP send API (M23). India-first, like the rest of the
 * defaults (D7). Credentials come from settings, never `.env`.
 */
class Msg91Gateway implements SmsGateway
{
    private const API = 'https://api.msg91.com/api/sendhttp.php';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function key(): string
    {
        return 'msg91';
    }

    public function label(): string
    {
        return 'MSG91';
    }

    public function isConfigured(): bool
    {
        return $this->settings->string('sms.msg91_auth_key') !== ''
            && $this->settings->string('sms.msg91_sender') !== '';
    }

    public function send(string $phone, string $body): SmsResult
    {
        try {
            $response = Http::timeout(15)->asForm()->get(self::API, [
                'authkey' => $this->settings->string('sms.msg91_auth_key'),
                'mobiles' => $this->digits($phone),
                'message' => $body,
                'sender' => $this->settings->string('sms.msg91_sender'),
                'route' => $this->settings->string('sms.msg91_route', '4'),
                'country' => '91',
            ]);

            if ($response->failed()) {
                return SmsResult::failed('HTTP '.$response->status(), ['body' => $response->body()]);
            }

            return SmsResult::sent(['body' => $response->body()]);
        } catch (Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }
    }

    private function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }
}
