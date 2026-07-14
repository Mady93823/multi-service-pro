<?php

namespace App\Domain\Comms\Gateways;

use App\Domain\Comms\Contracts\SmsGateway;
use App\Domain\Comms\SmsResult;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Twilio over its REST API (M23) — the non-India option. Raw HTTP with basic
 * auth; no SDK (D15).
 */
class TwilioGateway implements SmsGateway
{
    private const API = 'https://api.twilio.com/2010-04-01/Accounts';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function key(): string
    {
        return 'twilio';
    }

    public function label(): string
    {
        return 'Twilio';
    }

    public function isConfigured(): bool
    {
        return $this->settings->string('sms.twilio_sid') !== ''
            && $this->settings->string('sms.twilio_token') !== ''
            && $this->settings->string('sms.twilio_from') !== '';
    }

    public function send(string $phone, string $body): SmsResult
    {
        $sid = $this->settings->string('sms.twilio_sid');

        try {
            $response = Http::withBasicAuth($sid, $this->settings->string('sms.twilio_token'))
                ->timeout(15)
                ->asForm()
                ->post(self::API.'/'.$sid.'/Messages.json', [
                    'From' => $this->settings->string('sms.twilio_from'),
                    'To' => $phone,
                    'Body' => $body,
                ]);

            if ($response->failed()) {
                return SmsResult::failed('HTTP '.$response->status(), ['body' => $response->body()]);
            }

            /** @var array<string, mixed> $json */
            $json = $response->json() ?? [];

            return SmsResult::sent($json);
        } catch (Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }
    }
}
