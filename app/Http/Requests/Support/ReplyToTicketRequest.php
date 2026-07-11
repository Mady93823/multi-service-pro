<?php

namespace App\Http\Requests\Support;

use App\Domain\Settings\SettingsRegistry;
use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;

class ReplyToTicketRequest extends FormRequest
{
    /**
     * The policy owns the closed-tickets-read-only gate; ReplyToTicket
     * re-checks under a lock for the race.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->ticketParam()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAttachments = app(SettingsRegistry::class)->integer('support.max_attachments', 3);

        return [
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:'.$maxAttachments],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxAttachments = app(SettingsRegistry::class)->integer('support.max_attachments', 3);

        return [
            'attachments.max' => __('You can attach up to :max files.', ['max' => (string) $maxAttachments]),
            'attachments.*.max' => __('Each file must be 4 MB or smaller.'),
        ];
    }

    private function ticketParam(): SupportTicket
    {
        $ticket = $this->route('ticket');

        assert($ticket instanceof SupportTicket);

        return $ticket;
    }
}
