<?php

namespace App\Notifications;

use App\Domain\Comms\EmailTemplateRenderer;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\NotificationChannels;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Every notification the platform sends (M23).
 *
 * Before this, each class repeated its own via() and re-derived the same
 * title/body for three channels. Now a subclass names its event and fills one
 * payload; the base turns that payload into the in-app row, the Reverb
 * broadcast, the push, the SMS and the email.
 *
 * The email is the interesting one (ADR D25): an admin's `email_templates` row
 * is rendered *if it exists and renders*, and the shipped default underneath is
 * used otherwise. There is no state in which a booking confirmation is lost
 * because someone mangled a template.
 */
abstract class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function event(): NotificationEvent;

    /**
     * The in-app payload. `title`, `body` and `url` are load-bearing — every
     * other channel is derived from them.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationChannels::class)->for($this->event(), $notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        $variables = $this->variables($notifiable);

        return [
            'title' => $variables['title'],
            'body' => $variables['body'],
            'url' => $variables['url'],
        ];
    }

    public function toSms(object $notifiable): string
    {
        $variables = $this->variables($notifiable);

        return trim($variables['title'].' — '.$variables['body']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $variables = $this->variables($notifiable);

        $rendered = app(EmailTemplateRenderer::class)->render($this->event(), $variables);

        if ($rendered !== null) {
            return (new MailMessage)
                ->subject($rendered['subject'])
                ->view('mail.template', [
                    'appName' => $variables['app_name'],
                    // Already markdown-rendered and HTML-stripped (D20).
                    'content' => new HtmlString($rendered['html']),
                    'url' => $variables['url'],
                ]);
        }

        // The shipped default: whatever happens to the template, this sends.
        $mail = (new MailMessage)
            ->subject($variables['title'])
            ->greeting(__('Hi :name,', ['name' => $variables['name']]))
            ->line($variables['body']);

        return $variables['url'] === '' ? $mail : $mail->action(__('View details'), $variables['url']);
    }

    /**
     * The payload flattened to strings — what an email template's placeholders
     * are substituted from, and what NotificationEvent::variables() advertises.
     *
     * @return array<string, string>
     */
    public function variables(object $notifiable): array
    {
        $variables = ['title' => '', 'body' => '', 'url' => ''];

        foreach ($this->toArray($notifiable) as $key => $value) {
            if (is_scalar($value)) {
                $variables[(string) $key] = (string) $value;
            }
        }

        $variables['app_name'] = app(SettingsRegistry::class)
            ->string('branding.app_name', (string) config('app.name'));
        $variables['name'] = $notifiable instanceof User ? $notifiable->name : '';

        return $variables;
    }
}
