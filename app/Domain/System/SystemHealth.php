<?php

namespace App\Domain\System;

use App\Domain\Comms\MailConfigurator;
use App\Domain\Comms\SmsManager;
use App\Domain\Settings\SettingsRegistry;
use App\Notifications\FcmChannel;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What an operator needs to see before they believe an install is healthy (M24).
 *
 * The distinction that matters here is **broken** vs **not configured**. Debug
 * mode left on in production is broken. No SMS gateway is not — it is a choice
 * the product supports, and the screen says "off", not "error". Only the first
 * kind is allowed to be red, or an operator learns to ignore the page.
 */
class SystemHealth
{
    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly MailConfigurator $mail,
        private readonly SmsManager $sms,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: string, status: string}>
     */
    public function checks(): array
    {
        return [
            // The version is informational: composer already refuses to install
            // on a PHP this app cannot run on, so a running app has a good one.
            $this->check('php', __('PHP version'), PHP_VERSION, 'ok'),
            $this->check('database', __('Database'), $this->database(), $this->database() === __('Unreachable') ? 'error' : 'ok'),
            // A production install with debug on prints stack traces — including
            // credentials — to whoever triggers an error.
            $this->check('debug', __('Debug mode'), config('app.debug') ? __('On') : __('Off'), config('app.debug') && app()->environment('production') ? 'error' : 'ok'),
            $this->check('app_url', __('App URL'), (string) config('app.url'), 'ok'),
            $this->check('queue', __('Queue driver'), (string) config('queue.default'), config('queue.default') === 'sync' ? 'warning' : 'ok'),
            $this->check('broadcast', __('Realtime driver'), (string) config('broadcasting.default'), config('broadcasting.default') === 'reverb' ? 'ok' : 'warning'),
            $this->check('storage_link', __('Public storage link'), is_link(public_path('storage')) || is_dir(public_path('storage')) ? __('Linked') : __('Missing'), is_link(public_path('storage')) || is_dir(public_path('storage')) ? 'ok' : 'error'),
            $this->check('storage_writable', __('Storage writable'), is_writable(storage_path()) ? __('Yes') : __('No'), is_writable(storage_path()) ? 'ok' : 'error'),
            // Optional integrations: "off" is a supported state, never an error.
            $this->check('mail', __('Email'), $this->mail->isConfigured() ? __('Configured') : __('Not set up'), 'ok'),
            $this->check('sms', __('SMS'), $this->sms->isConfigured() ? __('Configured') : __('Not set up'), 'ok'),
            $this->check('push', __('Push (FCM)'), FcmChannel::isConfigured() ? __('Configured') : __('Not set up'), 'ok'),
        ];
    }

    /**
     * @return array{version: string, php: string, laravel: string, timezone: string, locale: string}
     */
    public function about(): array
    {
        return [
            'version' => (string) config('app.version', '1.0.0'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'timezone' => $this->settings->string('localization.timezone', 'Asia/Kolkata'),
            'locale' => $this->settings->string('localization.locale', 'en'),
        ];
    }

    private function database(): string
    {
        try {
            DB::connection()->getPdo();

            return (string) DB::connection()->getDriverName();
        } catch (Throwable) {
            return __('Unreachable');
        }
    }

    /**
     * @return array{key: string, label: string, value: string, status: string}
     */
    private function check(string $key, string $label, string $value, string $status): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'status' => $status];
    }
}
