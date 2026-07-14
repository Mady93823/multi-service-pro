<?php

namespace App\Domain\Comms;

use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\User;
use App\Notifications\FcmChannel;
use App\Notifications\SmsChannel;

/**
 * The one place a notification's via() is decided (M23).
 *
 * Before this, every notification class repeated the same `['database',
 * 'broadcast']` plus an FCM check. Now the rule lives once: in-app channels are
 * always on, and each optional channel joins only when the *provider is
 * configured* (D14: an install with no mail, no SMS gateway and no Firebase must
 * send nothing rather than 500) **and** the preference for this event allows it.
 *
 * Configuration is checked before preference on purpose — an admin turning mail
 * on for an install with no SMTP must not queue mail into a void.
 */
class NotificationChannels
{
    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly MailConfigurator $mail,
        private readonly SmsManager $sms,
    ) {}

    /**
     * @return list<string>
     */
    public function for(NotificationEvent $event, object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        $user = $notifiable instanceof User ? $notifiable : null;

        if ($this->mail->isConfigured()
            && $this->hasEmail($notifiable)
            && $this->preferences->enabled($event, NotificationChannel::Mail, $user)) {
            $channels[] = 'mail';
        }

        if ($this->sms->isConfigured()
            && $this->phone($notifiable) !== null
            && $this->preferences->enabled($event, NotificationChannel::Sms, $user)) {
            $channels[] = SmsChannel::class;
        }

        if (FcmChannel::isConfigured()
            && $this->preferences->enabled($event, NotificationChannel::Fcm, $user)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function phone(object $notifiable): ?string
    {
        $phone = $notifiable instanceof User ? $notifiable->phone : null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    private function hasEmail(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->email !== '';
    }
}
