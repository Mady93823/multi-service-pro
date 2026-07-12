<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Http\Request;

class SupportGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'support';
    }

    public function label(): string
    {
        return __('Support');
    }

    public function description(): string
    {
        return __('Helpdesk limits and the canned responses the reply box offers.');
    }

    public function keys(): array
    {
        return ['support.max_attachments', 'support.canned_responses'];
    }

    public function rules(Request $request): array
    {
        return [
            'support_max_attachments' => ['required', 'integer', 'min:0', 'max:10'],
            'support_canned_responses' => ['nullable', 'array', 'max:20'],
            'support_canned_responses.*.title' => ['required', 'string', 'max:100'],
            'support_canned_responses.*.body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function values(): array
    {
        return [
            'support_max_attachments' => $this->settings->integer('support.max_attachments', 3),
            'support_canned_responses' => $this->settings->get('support.canned_responses') ?? [],
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('support.max_attachments', $data['support_max_attachments']);

        /** @var array<int, array{title: string, body: string}> $canned */
        $canned = $data['support_canned_responses'] ?? [];

        $this->settings->set('support.canned_responses', array_values(array_map(
            fn (array $response): array => ['title' => $response['title'], 'body' => $response['body']],
            $canned,
        )));
    }
}
