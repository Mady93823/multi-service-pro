<?php

namespace App\Domain\Settings\Groups;

class PaymentsGroup extends SettingsGroup
{
    /**
     * Write-only gateway secrets: form field => settings key. These are never
     * rendered back to the admin, so a blank submission means "keep what is
     * stored" and the paired remove_* flag is the only way to erase one (M08).
     */
    private const SECRETS = [
        'razorpay_key_secret' => 'payments.razorpay_key_secret',
        'razorpay_webhook_secret' => 'payments.razorpay_webhook_secret',
        'stripe_secret_key' => 'payments.stripe_secret_key',
        'stripe_webhook_secret' => 'payments.stripe_webhook_secret',
    ];

    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return __('Payments');
    }

    public function description(): string
    {
        return __('Which methods customers may use, the tax applied to every booking, and the gateway credentials.');
    }

    public function keys(): array
    {
        return [
            'payments.tax_label',
            'payments.tax_percent',
            'payments.pay_after_service',
            'payments.wallet_enabled',
            'payments.offline_enabled',
            'payments.offline_instructions',
            'payments.razorpay_key_id',
            'payments.razorpay_key_secret',
            'payments.razorpay_webhook_secret',
            'payments.stripe_publishable_key',
            'payments.stripe_secret_key',
            'payments.stripe_webhook_secret',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'tax_label' => ['required', 'string', 'max:20'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pay_after_service' => ['boolean'],
            'wallet_enabled' => ['boolean'],
            'offline_enabled' => ['boolean'],
            'offline_instructions' => ['nullable', 'string', 'max:2000'],
            'razorpay_key_id' => ['nullable', 'string', 'max:191'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:191'],
            // Write-only. Blank keeps the stored secret; remove_* erases it.
            'razorpay_key_secret' => ['nullable', 'string', 'max:191'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:191'],
            'stripe_secret_key' => ['nullable', 'string', 'max:191'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:191'],
            'remove_razorpay_key_secret' => ['boolean'],
            'remove_razorpay_webhook_secret' => ['boolean'],
            'remove_stripe_secret_key' => ['boolean'],
            'remove_stripe_webhook_secret' => ['boolean'],
        ];
    }

    public function values(): array
    {
        $values = [
            'tax_label' => $this->settings->string('payments.tax_label', 'GST'),
            'tax_percent' => $this->settings->decimal('payments.tax_percent', 18.0),
            'pay_after_service' => $this->settings->boolean('payments.pay_after_service', true),
            'wallet_enabled' => $this->settings->boolean('payments.wallet_enabled', true),
            'offline_enabled' => $this->settings->boolean('payments.offline_enabled', false),
            'offline_instructions' => $this->settings->string('payments.offline_instructions'),
            // Publishable halves of each key pair are safe to render.
            'razorpay_key_id' => $this->settings->string('payments.razorpay_key_id'),
            'stripe_publishable_key' => $this->settings->string('payments.stripe_publishable_key'),
        ];

        // Gateway secrets never leave the server: Inertia serializes every prop
        // into the page HTML. Send only whether one is stored.
        foreach (self::SECRETS as $field => $settingKey) {
            $values[$field.'_set'] = $this->settings->string($settingKey) !== '';
        }

        return $values;
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('payments.tax_label', $data['tax_label']);
        $this->settings->set('payments.tax_percent', $data['tax_percent']);
        $this->settings->set('payments.pay_after_service', $this->toggle($data, 'pay_after_service'));
        $this->settings->set('payments.wallet_enabled', $this->toggle($data, 'wallet_enabled'));
        $this->settings->set('payments.offline_enabled', $this->toggle($data, 'offline_enabled'));
        $this->settings->set('payments.offline_instructions', $data['offline_instructions'] ?? null);
        $this->settings->set('payments.razorpay_key_id', $data['razorpay_key_id'] ?? null);
        $this->settings->set('payments.stripe_publishable_key', $data['stripe_publishable_key'] ?? null);

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
