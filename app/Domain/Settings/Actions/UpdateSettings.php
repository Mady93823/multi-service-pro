<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateSettings
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @param  array<string, mixed>  $data  validated UpdateSettingsRequest payload
     */
    public function handle(array $data, ?UploadedFile $logo = null): void
    {
        $this->settings->set('branding.app_name', $data['app_name']);
        $this->settings->set('branding.primary_color', $data['primary_color'] ?? null);
        $this->settings->set('localization.currency', $data['currency']);
        $this->settings->set('localization.timezone', $data['timezone']);
        $this->settings->set('localization.locale', $data['locale']);
        $this->settings->set('booking.code_prefix', $data['booking_code_prefix']);
        $this->settings->set('booking.slot_minutes', $data['slot_minutes']);
        $this->settings->set('booking.day_starts', $data['day_starts']);
        $this->settings->set('booking.day_ends', $data['day_ends']);
        $this->settings->set('booking.lead_time_hours', $data['lead_time_hours']);
        $this->settings->set('booking.max_days_ahead', $data['max_days_ahead']);
        $this->settings->set('booking.job_otp_required', (bool) ($data['job_otp_required'] ?? false));
        $this->settings->set('booking.free_cancel_hours', $data['free_cancel_hours']);
        $this->settings->set('booking.cancellation_fee_type', $data['cancellation_fee_type']);
        $this->settings->set('booking.cancellation_fee_value', $data['cancellation_fee_value']);
        $this->settings->set('booking.reschedule_min_hours', $data['reschedule_min_hours']);
        $this->settings->set('payments.tax_label', $data['tax_label']);
        $this->settings->set('payments.tax_percent', $data['tax_percent']);

        $currentLogo = $this->settings->string('branding.logo_path');

        if ($logo !== null) {
            $path = $logo->store('branding', 'public');
            $this->settings->set('branding.logo_path', $path !== false ? $path : null);
            $this->deleteFile($currentLogo);
        } elseif (($data['remove_logo'] ?? false) && $currentLogo !== '') {
            $this->settings->set('branding.logo_path', null);
            $this->deleteFile($currentLogo);
        }
    }

    private function deleteFile(string $path): void
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
