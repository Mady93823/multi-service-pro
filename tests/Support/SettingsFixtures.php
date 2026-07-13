<?php

namespace Tests\Support;

/**
 * Valid PUT payloads, one per settings group (ADR D24).
 *
 * The old single-payload fixture was landmine 13: every new required setting,
 * anywhere, had to be added here or every settings save in the suite started
 * 422ing. Now a new key only touches its own group's payload — and if the group
 * itself is new, `SettingsGroupCoverageTest` fails loudly instead.
 */
class SettingsFixtures
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function payloads(): array
    {
        return [
            'branding' => [
                'app_name' => 'Acme Services',
                'primary_color' => '#4f46e5',
            ],
            'localization' => [
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
            ],
            'features' => [
                'otp_required' => false,
            ],
            'booking' => [
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
                'payment_timeout_minutes' => 30,
            ],
            'dispatch' => [
                'dispatch_mode' => 'nearest',
                'dispatch_offer_timeout_seconds' => 60,
                'dispatch_max_rounds' => 5,
                'dispatch_auto' => true,
            ],
            'tracking' => [
                'ping_interval_seconds' => 3,
                'min_move_meters' => 5,
                'max_accuracy_meters' => 100,
                'stale_after_seconds' => 30,
                'points_retention_days' => 30,
            ],
            'payments' => [
                'tax_label' => 'GST',
                'tax_percent' => 18,
                'pay_after_service' => true,
                'wallet_enabled' => true,
            ],
            'payouts' => [
                'commission_percent' => 20,
                'payouts_enabled' => true,
                'payout_min_amount' => 500,
                'payout_hold_days' => 7,
            ],
            'invoice' => [
                'invoice_prefix' => 'INV',
            ],
            'reviews' => [
                'reviews_enabled' => true,
                'reviews_max_photos' => 4,
            ],
            'referrals' => [
                'referrals_enabled' => true,
                'referrals_reward_amount' => 100,
            ],
            'support' => [
                'support_max_attachments' => 3,
            ],
            'appearance' => [
                'header_variant' => 'classic',
                'sticky_header' => true,
                'footer_variant' => 'columns',
            ],
            'social' => [
                'facebook' => 'https://facebook.com/acme',
            ],
            'cookie' => [
                'enabled' => false,
            ],
            'custom_code' => [
                'enabled' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(string $group, array $overrides = []): array
    {
        return array_merge(self::payloads()[$group] ?? [], $overrides);
    }
}
