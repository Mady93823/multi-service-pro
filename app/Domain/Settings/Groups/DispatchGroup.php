<?php

namespace App\Domain\Settings\Groups;

class DispatchGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'dispatch';
    }

    public function label(): string
    {
        return __('Dispatch');
    }

    public function description(): string
    {
        return __('How a placed booking finds a professional.');
    }

    public function keys(): array
    {
        return ['dispatch.mode', 'dispatch.offer_timeout_seconds', 'dispatch.max_rounds', 'dispatch.auto'];
    }

    public function rules(array $input): array
    {
        return [
            'dispatch_mode' => ['required', 'in:nearest,broadcast'],
            'dispatch_offer_timeout_seconds' => ['required', 'integer', 'min:15', 'max:600'],
            'dispatch_max_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'dispatch_auto' => ['boolean'],
        ];
    }

    public function values(): array
    {
        return [
            'dispatch_mode' => $this->settings->string('dispatch.mode', 'nearest'),
            'dispatch_offer_timeout_seconds' => $this->settings->integer('dispatch.offer_timeout_seconds', 60),
            'dispatch_max_rounds' => $this->settings->integer('dispatch.max_rounds', 5),
            'dispatch_auto' => $this->settings->boolean('dispatch.auto', true),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('dispatch.mode', $data['dispatch_mode']);
        $this->settings->set('dispatch.offer_timeout_seconds', $data['dispatch_offer_timeout_seconds']);
        $this->settings->set('dispatch.max_rounds', $data['dispatch_max_rounds']);
        $this->settings->set('dispatch.auto', $this->toggle($data, 'dispatch_auto'));
    }
}
