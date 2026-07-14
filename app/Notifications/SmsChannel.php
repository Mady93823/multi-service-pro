<?php

namespace App\Notifications;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\SmsManager;
use App\Domain\Comms\SmsResult;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a notification as an SMS through the configured gateway (M23).
 *
 * Two rules, both borrowed from things that already bit us:
 *
 * 1. **It never throws.** A dead SMS provider must not fail the booking that
 *    triggered the message — the same rule that makes a dead Reverb never fail
 *    a tracking ping (M07). Failures land in `sms_logs` and the log.
 * 2. **It is inert when unconfigured.** `via()` never even lists this channel
 *    without an active gateway (D14), and this re-checks anyway.
 */
class SmsChannel
{
    public function __construct(private readonly SmsManager $manager) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $gateway = $this->manager->active();

        if ($gateway === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable instanceof User ? (string) $notifiable->phone : '';

        if ($phone === '') {
            return;
        }

        $body = (string) $notification->toSms($notifiable);

        try {
            $result = $gateway->send($phone, $body);
        } catch (Throwable $e) {
            // A driver is supposed to swallow its own failures; if one does not,
            // the message dies here rather than in the job that queued it.
            $result = SmsResult::failed($e->getMessage());
        }

        if (! $result->sent) {
            Log::warning('SMS delivery failed.', [
                'gateway' => $gateway->key(),
                'error' => $result->error,
            ]);
        }

        SmsLog::query()->create([
            'user_id' => $notifiable instanceof User ? $notifiable->id : null,
            'phone' => $phone,
            'event_key' => $this->eventKey($notification),
            'body' => $body,
            'gateway' => $gateway->key(),
            'status' => $result->sent ? SmsLog::STATUS_SENT : SmsLog::STATUS_FAILED,
            'response' => $result->error === null
                ? $result->response
                : ['error' => $result->error] + $result->response,
        ]);
    }

    private function eventKey(Notification $notification): string
    {
        if (! method_exists($notification, 'event')) {
            return 'unknown';
        }

        $event = $notification->event();

        return $event instanceof NotificationEvent ? $event->value : 'unknown';
    }
}
