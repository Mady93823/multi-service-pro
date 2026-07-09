<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(SettingsRegistry $settings): Response
    {
        $logoPath = $settings->string('branding.logo_path');

        return Inertia::render('admin/settings/edit', [
            'values' => [
                'app_name' => $settings->string('branding.app_name', (string) config('app.name')),
                'primary_color' => $settings->string('branding.primary_color') ?: null,
                'currency' => $settings->string('localization.currency', 'INR'),
                'timezone' => $settings->string('localization.timezone', 'Asia/Kolkata'),
                'locale' => $settings->string('localization.locale', 'en'),
                'logo_url' => $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null,
                'booking_code_prefix' => $settings->string('booking.code_prefix', 'BK'),
                'slot_minutes' => $settings->integer('booking.slot_minutes', 60),
                'day_starts' => $settings->string('booking.day_starts', '08:00'),
                'day_ends' => $settings->string('booking.day_ends', '20:00'),
                'lead_time_hours' => $settings->integer('booking.lead_time_hours', 2),
                'max_days_ahead' => $settings->integer('booking.max_days_ahead', 7),
                'job_otp_required' => $settings->boolean('booking.job_otp_required', true),
                'free_cancel_hours' => $settings->integer('booking.free_cancel_hours', 2),
                'cancellation_fee_type' => $settings->string('booking.cancellation_fee_type', 'percent'),
                'cancellation_fee_value' => $settings->decimal('booking.cancellation_fee_value', 10.0),
                'reschedule_min_hours' => $settings->integer('booking.reschedule_min_hours', 2),
                'tax_label' => $settings->string('payments.tax_label', 'GST'),
                'tax_percent' => $settings->decimal('payments.tax_percent', 18.0),
                'pay_after_service' => $settings->boolean('payments.pay_after_service', true),
                'wallet_enabled' => $settings->boolean('payments.wallet_enabled', true),
                'payment_timeout_minutes' => $settings->integer('booking.payment_timeout_minutes', 30),
                // Publishable halves of each key pair are safe to render.
                'razorpay_key_id' => $settings->string('payments.razorpay_key_id'),
                'stripe_publishable_key' => $settings->string('payments.stripe_publishable_key'),
                // Gateway secrets never leave the server: Inertia serializes
                // every prop into the page HTML. Send only whether one is
                // stored; UpdateSettings treats a blank submission as "keep".
                'razorpay_key_secret_set' => $settings->string('payments.razorpay_key_secret') !== '',
                'razorpay_webhook_secret_set' => $settings->string('payments.razorpay_webhook_secret') !== '',
                'stripe_secret_key_set' => $settings->string('payments.stripe_secret_key') !== '',
                'stripe_webhook_secret_set' => $settings->string('payments.stripe_webhook_secret') !== '',
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettings $action): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->safe()->except(['logo']);

        $logo = $request->file('logo');
        $action->handle($data, is_array($logo) ? null : $logo);

        return back()->with('success', __('Settings saved.'));
    }
}
