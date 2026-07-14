<?php

namespace App\Notifications;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging push channel (M11).
 *
 * The platform must run with Firebase unconfigured (project constraint), so
 * this channel is inert until credentials are present: `via()` on each
 * notification only lists it when {@see self::isConfigured()} is true, and the
 * send path no-ops (with a debug log) otherwise. Wiring the real HTTP v1 send
 * lands when the client provides a service account (client doc §10) — no
 * hard dependency on a Firebase SDK is introduced before then.
 */
class FcmChannel
{
    /**
     * Credentials come from the API-keys screen first (M24) and from the
     * environment second, so a buyer can paste the Firebase service-account JSON
     * into the browser without editing `.env` — and an install with neither
     * simply never selects this channel (D14).
     */
    public static function isConfigured(): bool
    {
        return app(SettingsRegistry::class)->string('integrations.fcm_credentials') !== ''
            || (string) config('services.fcm.credentials') !== '';
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::isConfigured()) {
            return;
        }

        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $notification->toFcm($notifiable);

        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        // Credentials present but the actual FCM transport is not wired yet —
        // record intent so nothing is silently lost once the SDK is added.
        Log::debug('FCM push queued (transport pending Firebase setup).', [
            'notification' => $notification::class,
            'payload' => $payload,
        ]);
    }
}
