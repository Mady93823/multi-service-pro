<?php

namespace App\Domain\Settings\Groups;

use Illuminate\Validation\Rule;

class BookingGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'booking';
    }

    public function label(): string
    {
        return __('Booking');
    }

    public function description(): string
    {
        return __('Slot grid, booking window, job start code and cancellation rules.');
    }

    public function keys(): array
    {
        return [
            'booking.code_prefix',
            'booking.slot_minutes',
            'booking.day_starts',
            'booking.day_ends',
            'booking.lead_time_hours',
            'booking.max_days_ahead',
            'booking.job_otp_required',
            'booking.free_cancel_hours',
            'booking.cancellation_fee_type',
            'booking.cancellation_fee_value',
            'booking.reschedule_min_hours',
            'booking.payment_timeout_minutes',
        ];
    }

    public function rules(array $input): array
    {
        return [
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
                Rule::when(($input['cancellation_fee_type'] ?? null) === 'percent', ['max:100']),
            ],
            'reschedule_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'payment_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ];
    }

    public function values(): array
    {
        return [
            'booking_code_prefix' => $this->settings->string('booking.code_prefix', 'BK'),
            'slot_minutes' => $this->settings->integer('booking.slot_minutes', 60),
            'day_starts' => $this->settings->string('booking.day_starts', '08:00'),
            'day_ends' => $this->settings->string('booking.day_ends', '20:00'),
            'lead_time_hours' => $this->settings->integer('booking.lead_time_hours', 2),
            'max_days_ahead' => $this->settings->integer('booking.max_days_ahead', 7),
            'job_otp_required' => $this->settings->boolean('booking.job_otp_required', true),
            'free_cancel_hours' => $this->settings->integer('booking.free_cancel_hours', 2),
            'cancellation_fee_type' => $this->settings->string('booking.cancellation_fee_type', 'percent'),
            'cancellation_fee_value' => $this->settings->decimal('booking.cancellation_fee_value', 10.0),
            'reschedule_min_hours' => $this->settings->integer('booking.reschedule_min_hours', 2),
            'payment_timeout_minutes' => $this->settings->integer('booking.payment_timeout_minutes', 30),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('booking.code_prefix', $data['booking_code_prefix']);
        $this->settings->set('booking.slot_minutes', $data['slot_minutes']);
        $this->settings->set('booking.day_starts', $data['day_starts']);
        $this->settings->set('booking.day_ends', $data['day_ends']);
        $this->settings->set('booking.lead_time_hours', $data['lead_time_hours']);
        $this->settings->set('booking.max_days_ahead', $data['max_days_ahead']);
        $this->settings->set('booking.job_otp_required', $this->toggle($data, 'job_otp_required'));
        $this->settings->set('booking.free_cancel_hours', $data['free_cancel_hours']);
        $this->settings->set('booking.cancellation_fee_type', $data['cancellation_fee_type']);
        $this->settings->set('booking.cancellation_fee_value', $data['cancellation_fee_value']);
        $this->settings->set('booking.reschedule_min_hours', $data['reschedule_min_hours']);
        $this->settings->set('booking.payment_timeout_minutes', $data['payment_timeout_minutes']);
    }
}
