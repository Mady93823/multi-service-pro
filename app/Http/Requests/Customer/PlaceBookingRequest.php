<?php

namespace App\Http\Requests\Customer;

use App\Domain\Bookings\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceBookingRequest extends FormRequest
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
        $user = $this->user();

        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $user === null ? 0 : $user->id),
            ],
            'scheduled_at' => ['required', 'date', 'after:now'],
            // Only pay-after-service until the M08 gateways land.
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.max' => __('You can attach up to 4 photos.'),
            'photos.*.max' => __('Each photo must be 4 MB or smaller.'),
        ];
    }
}
