<?php

namespace App\Domain\Settings\Groups;

class ReferralsGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'referrals';
    }

    public function label(): string
    {
        return __('Referrals');
    }

    public function description(): string
    {
        return __('Wallet credit for customers whose invited friends complete a first booking.');
    }

    public function keys(): array
    {
        return ['referrals.enabled', 'referrals.reward_amount'];
    }

    public function rules(array $input): array
    {
        return [
            'referrals_enabled' => ['boolean'],
            'referrals_reward_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    public function values(): array
    {
        return [
            'referrals_enabled' => $this->settings->boolean('referrals.enabled', true),
            'referrals_reward_amount' => $this->settings->decimal('referrals.reward_amount', 100.0),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('referrals.enabled', $this->toggle($data, 'referrals_enabled'));
        $this->settings->set('referrals.reward_amount', $data['referrals_reward_amount']);
    }
}
