<?php

namespace App\Domain\Comms;

use App\Domain\Comms\Contracts\SmsGateway;
use App\Domain\Comms\Gateways\Msg91Gateway;
use App\Domain\Comms\Gateways\TwilioGateway;
use App\Domain\Settings\SettingsRegistry;

/**
 * The SMS drivers, and which one is live (M23) — GatewayManager's twin for
 * messages instead of money.
 *
 * `active()` returns null unless an admin picked a driver *and* filled its
 * credentials in. That null is what keeps the `sms` channel out of via() on a
 * fresh install (D14): no gateway, no channel, nothing to 500 on.
 */
class SmsManager
{
    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly Msg91Gateway $msg91,
        private readonly TwilioGateway $twilio,
    ) {}

    /**
     * @return list<SmsGateway>
     */
    public function all(): array
    {
        return [$this->msg91, $this->twilio];
    }

    public function active(): ?SmsGateway
    {
        $selected = $this->settings->string('sms.gateway', 'none');

        foreach ($this->all() as $gateway) {
            if ($gateway->key() === $selected && $gateway->isConfigured()) {
                return $gateway;
            }
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->active() !== null;
    }
}
