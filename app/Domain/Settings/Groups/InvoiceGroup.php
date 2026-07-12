<?php

namespace App\Domain\Settings\Groups;

class InvoiceGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'invoice';
    }

    public function label(): string
    {
        return __('Invoice');
    }

    public function description(): string
    {
        return __('Printed on every tax invoice. Leave the company name blank to use the app name.');
    }

    public function keys(): array
    {
        return [
            'invoice.prefix',
            'invoice.company_name',
            'invoice.gstin',
            'invoice.address',
            'invoice.state',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'invoice_prefix' => ['required', 'string', 'max:8', 'alpha_num:ascii'],
            'invoice_company_name' => ['nullable', 'string', 'max:150'],
            // 15 chars: 2 state + 10 PAN + 1 entity + 1 'Z' + 1 checksum.
            'invoice_gstin' => ['nullable', 'string', 'size:15', 'alpha_num:ascii'],
            'invoice_address' => ['nullable', 'string', 'max:255'],
            'invoice_state' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function values(): array
    {
        return [
            'invoice_prefix' => $this->settings->string('invoice.prefix', 'INV'),
            'invoice_company_name' => $this->settings->string('invoice.company_name') ?: null,
            'invoice_gstin' => $this->settings->string('invoice.gstin') ?: null,
            'invoice_address' => $this->settings->string('invoice.address') ?: null,
            'invoice_state' => $this->settings->string('invoice.state') ?: null,
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('invoice.prefix', $data['invoice_prefix']);
        $this->settings->set('invoice.company_name', $data['invoice_company_name'] ?? null);
        $this->settings->set('invoice.gstin', $data['invoice_gstin'] ?? null);
        $this->settings->set('invoice.address', $data['invoice_address'] ?? null);
        $this->settings->set('invoice.state', $data['invoice_state'] ?? null);
    }
}
