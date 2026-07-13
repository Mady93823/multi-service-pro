<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Typed, cached access to the settings table (D8 white-label registry).
 *
 * Falls back to shipped defaults when the database is unavailable or not yet
 * migrated — the web installer and early boot paths must never crash on a
 * missing settings table.
 */
class SettingsRegistry
{
    private const CACHE_KEY = 'settings.registry';

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    /**
     * Shipped defaults, also used to seed. Key => [group, type, value].
     *
     * @return array<string, array{group: string, type: SettingType, value: mixed}>
     */
    public static function defaults(): array
    {
        return [
            'branding.app_name' => ['group' => 'branding', 'type' => SettingType::String, 'value' => config('app.name')],
            'branding.logo_path' => ['group' => 'branding', 'type' => SettingType::String, 'value' => null],
            'branding.primary_color' => ['group' => 'branding', 'type' => SettingType::String, 'value' => null],
            'localization.currency' => ['group' => 'localization', 'type' => SettingType::String, 'value' => 'INR'],
            'localization.timezone' => ['group' => 'localization', 'type' => SettingType::String, 'value' => 'Asia/Kolkata'],
            'localization.locale' => ['group' => 'localization', 'type' => SettingType::String, 'value' => 'en'],
            'features.otp_required' => ['group' => 'features', 'type' => SettingType::Boolean, 'value' => false],
            'booking.code_prefix' => ['group' => 'booking', 'type' => SettingType::String, 'value' => 'BK'],
            'booking.slot_minutes' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 60],
            'booking.day_starts' => ['group' => 'booking', 'type' => SettingType::String, 'value' => '08:00'],
            'booking.day_ends' => ['group' => 'booking', 'type' => SettingType::String, 'value' => '20:00'],
            'booking.lead_time_hours' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 2],
            'booking.max_days_ahead' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 7],
            'booking.job_otp_required' => ['group' => 'booking', 'type' => SettingType::Boolean, 'value' => true],
            'booking.free_cancel_hours' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 2],
            'booking.cancellation_fee_type' => ['group' => 'booking', 'type' => SettingType::String, 'value' => 'percent'],
            'booking.cancellation_fee_value' => ['group' => 'booking', 'type' => SettingType::Decimal, 'value' => '10'],
            'booking.reschedule_min_hours' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 2],
            'booking.payment_timeout_minutes' => ['group' => 'booking', 'type' => SettingType::Integer, 'value' => 30],
            'dispatch.mode' => ['group' => 'dispatch', 'type' => SettingType::String, 'value' => 'nearest'],
            'dispatch.offer_timeout_seconds' => ['group' => 'dispatch', 'type' => SettingType::Integer, 'value' => 60],
            'dispatch.max_rounds' => ['group' => 'dispatch', 'type' => SettingType::Integer, 'value' => 5],
            'dispatch.auto' => ['group' => 'dispatch', 'type' => SettingType::Boolean, 'value' => true],
            'tracking.ping_interval_seconds' => ['group' => 'tracking', 'type' => SettingType::Integer, 'value' => 3],
            'tracking.min_move_meters' => ['group' => 'tracking', 'type' => SettingType::Integer, 'value' => 5],
            'tracking.max_accuracy_meters' => ['group' => 'tracking', 'type' => SettingType::Integer, 'value' => 100],
            'tracking.stale_after_seconds' => ['group' => 'tracking', 'type' => SettingType::Integer, 'value' => 30],
            'tracking.points_retention_days' => ['group' => 'tracking', 'type' => SettingType::Integer, 'value' => 30],
            'payments.tax_label' => ['group' => 'payments', 'type' => SettingType::String, 'value' => 'GST'],
            'payments.tax_percent' => ['group' => 'payments', 'type' => SettingType::Decimal, 'value' => '18'],
            'payments.pay_after_service' => ['group' => 'payments', 'type' => SettingType::Boolean, 'value' => true],
            'payments.wallet_enabled' => ['group' => 'payments', 'type' => SettingType::Boolean, 'value' => true],
            'payments.razorpay_key_id' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            'payments.razorpay_key_secret' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            'payments.razorpay_webhook_secret' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            'payments.stripe_publishable_key' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            'payments.stripe_secret_key' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            'payments.stripe_webhook_secret' => ['group' => 'payments', 'type' => SettingType::String, 'value' => null],
            // M09. Global commission rate; a category may override it.
            'payments.commission_percent' => ['group' => 'payments', 'type' => SettingType::Decimal, 'value' => '20'],
            'payouts.enabled' => ['group' => 'payouts', 'type' => SettingType::Boolean, 'value' => true],
            'payouts.min_amount' => ['group' => 'payouts', 'type' => SettingType::Decimal, 'value' => '500'],
            // Days a completed job's earning waits before it can be claimed —
            // the window in which a refund can still reverse it.
            'payouts.hold_days' => ['group' => 'payouts', 'type' => SettingType::Integer, 'value' => 7],
            // GST invoice header (D9). Blank company name falls back to the
            // branding app name, so an un-configured install still prints.
            'invoice.prefix' => ['group' => 'invoice', 'type' => SettingType::String, 'value' => 'INV'],
            'invoice.company_name' => ['group' => 'invoice', 'type' => SettingType::String, 'value' => null],
            'invoice.gstin' => ['group' => 'invoice', 'type' => SettingType::String, 'value' => null],
            'invoice.address' => ['group' => 'invoice', 'type' => SettingType::String, 'value' => null],
            'invoice.state' => ['group' => 'invoice', 'type' => SettingType::String, 'value' => null],
            // M10. Reviews & ratings; max_photos 0 turns photo uploads off.
            'reviews.enabled' => ['group' => 'reviews', 'type' => SettingType::Boolean, 'value' => true],
            'reviews.max_photos' => ['group' => 'reviews', 'type' => SettingType::Integer, 'value' => 4],
            // M12. Referral program: reward credited to the referrer's wallet
            // on the referee's first completed booking; 0 pauses payouts
            // without hiding the program.
            'referrals.enabled' => ['group' => 'referrals', 'type' => SettingType::Boolean, 'value' => true],
            'referrals.reward_amount' => ['group' => 'referrals', 'type' => SettingType::Decimal, 'value' => '100'],
            // M16. Helpdesk: attachment cap per message; canned responses are
            // a JSON list of {title, body} the admin reply box can insert.
            'support.max_attachments' => ['group' => 'support', 'type' => SettingType::Integer, 'value' => 3],
            'support.canned_responses' => ['group' => 'support', 'type' => SettingType::Json, 'value' => []],
            // M19. Storefront chrome: header/footer look, footer contact block
            // and the login page's side panel.
            'appearance.header_variant' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => 'classic'],
            'appearance.sticky_header' => ['group' => 'appearance', 'type' => SettingType::Boolean, 'value' => true],
            'appearance.footer_variant' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => 'columns'],
            'appearance.footer_about' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.copyright' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.contact_email' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.contact_phone' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.contact_address' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.login_headline' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.login_subcopy' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.login_image_url' => ['group' => 'appearance', 'type' => SettingType::String, 'value' => null],
            'appearance.newsletter_enabled' => ['group' => 'appearance', 'type' => SettingType::Boolean, 'value' => true],
            'social.facebook' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'social.instagram' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'social.x' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'social.youtube' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'social.linkedin' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'social.whatsapp' => ['group' => 'social', 'type' => SettingType::String, 'value' => null],
            'cookie.enabled' => ['group' => 'cookie', 'type' => SettingType::Boolean, 'value' => false],
            'cookie.message' => ['group' => 'cookie', 'type' => SettingType::String, 'value' => null],
            'cookie.accept_label' => ['group' => 'cookie', 'type' => SettingType::String, 'value' => null],
            'cookie.decline_label' => ['group' => 'cookie', 'type' => SettingType::String, 'value' => null],
            'cookie.policy_slug' => ['group' => 'cookie', 'type' => SettingType::String, 'value' => null],
            // Off by default and storefront-only: an admin snippet runs script on
            // every visitor's page (D26).
            'custom_code.enabled' => ['group' => 'custom_code', 'type' => SettingType::Boolean, 'value' => false],
            'custom_code.css' => ['group' => 'custom_code', 'type' => SettingType::String, 'value' => null],
            'custom_code.js' => ['group' => 'custom_code', 'type' => SettingType::String, 'value' => null],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function decimal(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $prefix = $group.'.';

        return array_filter(
            $this->all(),
            fn (string $key): bool => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function set(string $key, mixed $value, ?SettingType $type = null, ?string $group = null): void
    {
        $known = self::defaults()[$key] ?? null;
        $type ??= $known['type'] ?? SettingType::String;
        $group ??= $known['group'] ?? explode('.', $key)[0];

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'type' => $type->value, 'value' => $type->serialize($value)],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }

    /**
     * All settings, cast, defaults overlaid by DB rows.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $values = [];
        foreach (self::defaults() as $key => $definition) {
            $values[$key] = $definition['value'];
        }

        try {
            /** @var array<string, mixed> $stored */
            $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
                $rows = [];
                foreach (Setting::query()->get() as $setting) {
                    $type = SettingType::tryFrom($setting->type) ?? SettingType::String;
                    $rows[$setting->key] = $type->cast($setting->value);
                }

                return $rows;
            });

            $values = array_merge($values, $stored);
        } catch (Throwable) {
            // Database not reachable / not migrated yet (installer, early boot):
            // serve shipped defaults instead of crashing.
        }

        return $this->resolved = $values;
    }
}
