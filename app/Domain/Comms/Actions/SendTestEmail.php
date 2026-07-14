<?php

namespace App\Domain\Comms\Actions;

use App\Domain\Comms\EmailTemplateRenderer;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\MailConfigurator;
use App\Domain\Settings\SettingsRegistry;
use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Proves the SMTP settings before an admin trusts them (M23).
 *
 * Sent synchronously on purpose: a queued test would report "sent" and then
 * fail in a worker log nobody reads. The transport's own exception — bad host,
 * bad password, blocked port — is exactly what the admin needs, so it comes
 * back as a validation error on the form that caused it.
 *
 * With an event, it sends that event's template rendered from sample data, so
 * "test" and "real send" go down the same renderer (D25) and a template that
 * previews fine cannot break in production.
 */
class SendTestEmail
{
    public function __construct(
        private readonly MailConfigurator $mail,
        private readonly SettingsRegistry $settings,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function handle(string $email, ?NotificationEvent $event = null): void
    {
        if (! $this->mail->isConfigured()) {
            throw ValidationException::withMessages([
                'email' => __('Add an SMTP host and a from-address first.'),
            ]);
        }

        // The settings may have been saved a moment ago, in this same request.
        $this->mail->apply();

        $appName = $this->settings->string('branding.app_name', (string) config('app.name'));

        [$subject, $html] = $this->content($event, $appName);

        try {
            Mail::to($email)->send(new TestEmail($subject, $html));
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'email' => __('The email could not be sent: :error', ['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function content(?NotificationEvent $event, string $appName): array
    {
        if ($event !== null) {
            $rendered = $this->renderer->render($event, $event->sample());

            if ($rendered !== null) {
                return [$rendered['subject'], $rendered['html']];
            }

            // No usable template: the shipped default is what would really go
            // out, so say so rather than pretending the template worked.
            return [
                __('Test email from :app', ['app' => $appName]),
                '<p>'.e(__('This event has no working template, so :app sends its built-in email.', ['app' => $appName])).'</p>',
            ];
        }

        return [
            __('Test email from :app', ['app' => $appName]),
            '<p>'.e(__('This is a test email from :app. Your mail settings work.', ['app' => $appName])).'</p>',
        ];
    }
}
