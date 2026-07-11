<?php

namespace App\Http\Requests\Support;

use App\Domain\Settings\SettingsRegistry;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAttachments = app(SettingsRegistry::class)->integer('support.max_attachments', 3);

        return [
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            // Only bookings the requester took part in can be linked —
            // as the customer, or (for providers) as the assigned pro.
            'booking_id' => [
                'nullable',
                'integer',
                Rule::exists('bookings', 'id')->where(function ($query): void {
                    $query->where('customer_id', $this->user()?->id)
                        ->orWhere('provider_id', $this->user()?->id);
                }),
            ],
            'message' => ['required', 'string', 'max:5000'],
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
            'booking_id.exists' => __('That booking could not be found on your account.'),
        ];
    }
}
