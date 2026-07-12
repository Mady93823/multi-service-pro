<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Http\Request;

/**
 * Commission lives here rather than in Payments because an admin thinks of the
 * platform's cut and the professional's withdrawal as one policy — a screen
 * group may own keys from more than one storage group (D24).
 */
class PayoutsGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'payouts';
    }

    public function label(): string
    {
        return __('Commission and payouts');
    }

    public function description(): string
    {
        return __('The platform’s cut of each completed job, and how professionals withdraw what they have earned. A category can override the rate.');
    }

    public function keys(): array
    {
        return [
            'payments.commission_percent',
            'payouts.enabled',
            'payouts.min_amount',
            'payouts.hold_days',
        ];
    }

    public function rules(Request $request): array
    {
        return [
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payouts_enabled' => ['boolean'],
            'payout_min_amount' => ['required', 'numeric', 'min:0'],
            'payout_hold_days' => ['required', 'integer', 'min:0', 'max:90'],
        ];
    }

    public function values(): array
    {
        return [
            'commission_percent' => $this->settings->decimal('payments.commission_percent', 20.0),
            'payouts_enabled' => $this->settings->boolean('payouts.enabled', true),
            'payout_min_amount' => $this->settings->decimal('payouts.min_amount', 0.0),
            'payout_hold_days' => $this->settings->integer('payouts.hold_days', 7),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('payments.commission_percent', $data['commission_percent']);
        $this->settings->set('payouts.enabled', $this->toggle($data, 'payouts_enabled'));
        $this->settings->set('payouts.min_amount', $data['payout_min_amount']);
        $this->settings->set('payouts.hold_days', $data['payout_hold_days']);
    }
}
