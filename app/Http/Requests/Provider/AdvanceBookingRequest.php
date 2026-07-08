<?php

namespace App\Http\Requests\Provider;

use App\Domain\Bookings\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceBookingRequest extends FormRequest
{
    /**
     * Forward steps a provider may drive on their own job. Assignment
     * (searching → assigned) is dispatch's job; cancellations by other actors
     * are elsewhere. The state machine still validates the actual edge.
     *
     * @var list<string>
     */
    public const TARGETS = [
        'accepted',
        'en_route',
        'arrived',
        'in_progress',
        'completed',
        'cancelled_provider',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in(self::TARGETS)],
            // Job-start code the customer hands over (guarded in the machine).
            'otp' => ['nullable', 'string', 'digits:4'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function target(): BookingStatus
    {
        return BookingStatus::from((string) $this->validated('to'));
    }
}
