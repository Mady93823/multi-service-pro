<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[a-z]{2}([_-][A-Za-z]{2,4})?$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
            'booking_code_prefix' => ['required', 'string', 'max:8', 'alpha_num:ascii'],
            'slot_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'day_starts' => ['required', 'date_format:H:i'],
            'day_ends' => ['required', 'date_format:H:i', 'after:day_starts'],
            'lead_time_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:60'],
            'job_otp_required' => ['boolean'],
            'free_cancel_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'cancellation_fee_type' => ['required', 'in:flat,percent'],
            'cancellation_fee_value' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($this->input('cancellation_fee_type') === 'percent', ['max:100']),
            ],
            'reschedule_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'tax_label' => ['required', 'string', 'max:20'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pay_after_service' => ['boolean'],
            'wallet_enabled' => ['boolean'],
            'payment_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('The primary color must be a hex value like #4f46e5.'),
        ];
    }
}
