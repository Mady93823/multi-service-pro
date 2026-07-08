<?php

namespace App\Http\Requests\Admin;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Users\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin->value) ?? false;
    }

    /**
     * Legality of the transition itself is the state machine's job — this
     * request only shapes the input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::enum(BookingStatus::class)],
            'provider_id' => [
                'nullable',
                'integer',
                'required_if:to,'.BookingStatus::Assigned->value,
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
                'required_if:to,'.BookingStatus::CancelledAdmin->value,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $providerId = $this->input('provider_id');

            if ($providerId === null || $validator->errors()->has('provider_id')) {
                return;
            }

            $isProvider = User::query()
                ->whereKey($providerId)
                ->role(Role::Provider->value)
                ->exists();

            if (! $isProvider) {
                $validator->errors()->add('provider_id', __('The selected user is not a provider.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_id.required_if' => __('Pick a provider to assign this booking to.'),
            'note.required_if' => __('A reason is required when cancelling a booking.'),
        ];
    }
}
