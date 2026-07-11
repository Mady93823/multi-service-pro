<?php

namespace Tests\Support;

/**
 * The one canonical valid settings PUT payload (landmine 13: every new
 * required setting must be added HERE, and only here, or every settings
 * save in the suite starts 422ing).
 */
class SettingsFixtures
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function validPayload(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'Acme Services',
            'primary_color' => '#4f46e5',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'booking_code_prefix' => 'BK',
            'slot_minutes' => 60,
            'day_starts' => '08:00',
            'day_ends' => '20:00',
            'lead_time_hours' => 2,
            'max_days_ahead' => 7,
            'job_otp_required' => true,
            'free_cancel_hours' => 2,
            'cancellation_fee_type' => 'percent',
            'cancellation_fee_value' => 10,
            'reschedule_min_hours' => 2,
            'tax_label' => 'GST',
            'tax_percent' => 18,
            'payment_timeout_minutes' => 30,
            'pay_after_service' => true,
            'wallet_enabled' => true,
            'commission_percent' => 20,
            'payouts_enabled' => true,
            'payout_min_amount' => 500,
            'payout_hold_days' => 7,
            'invoice_prefix' => 'INV',
            'reviews_enabled' => true,
            'reviews_max_photos' => 4,
            'referrals_enabled' => true,
            'referrals_reward_amount' => 100,
        ], $overrides);
    }
}
