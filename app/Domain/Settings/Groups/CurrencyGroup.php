<?php

namespace App\Domain\Settings\Groups;

/**
 * How money is printed — and nothing else (ADR D23).
 *
 * There is **one currency per install**. No FX rates, no per-booking currency,
 * no conversion at checkout: a booking's money columns are snapshots in the
 * platform currency, and changing this screen changes how they are *displayed*,
 * never what they are worth. An operator who needs two currencies needs two
 * installs.
 *
 * The code itself lives in `localization.currency` and is owned here, so the
 * code and the way it is rendered can never drift apart on two screens.
 */
class CurrencyGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'currency';
    }

    public function label(): string
    {
        return __('Currency');
    }

    public function description(): string
    {
        return __('The currency this install runs on, and how amounts are printed. One currency per install — this is formatting, not conversion.');
    }

    public function keys(): array
    {
        return [
            'localization.currency',
            'currency.symbol',
            'currency.position',
            'currency.decimals',
            'currency.grouping',
        ];
    }

    public function rules(array $input): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'symbol' => ['required', 'string', 'max:5'],
            'position' => ['required', 'string', 'in:before,after'],
            'decimals' => ['required', 'integer', 'min:0', 'max:2'],
            // Indian grouping is 1,00,000 — not a locale detail we can leave to
            // ext-intl, which shared hosts often omit (D8).
            'grouping' => ['required', 'string', 'in:indian,western'],
        ];
    }

    public function values(): array
    {
        return [
            'code' => $this->settings->string('localization.currency', 'INR'),
            'symbol' => $this->settings->string('currency.symbol', '₹'),
            'position' => $this->settings->string('currency.position', 'before'),
            'decimals' => $this->settings->integer('currency.decimals', 2),
            'grouping' => $this->settings->string('currency.grouping', 'indian'),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('localization.currency', $data['code']);
        $this->settings->set('currency.symbol', $data['symbol']);
        $this->settings->set('currency.position', $data['position']);
        $this->settings->set('currency.decimals', $data['decimals']);
        $this->settings->set('currency.grouping', $data['grouping']);
    }
}
