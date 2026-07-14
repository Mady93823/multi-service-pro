<?php

namespace App\Domain\Comms;

use App\Domain\Cms\MarkdownRenderer;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders an admin's email template — or says it cannot (ADR D25).
 *
 * The whole point of this class is the `null` it returns. A template is an
 * *optional layer*: when the row is missing, disabled, empty or throws, this
 * hands back null and the notification sends its shipped default instead. A
 * mangled template must degrade one message's styling, never eat a booking
 * confirmation.
 *
 * Placeholders are `{{ name }}`. An unknown one renders as nothing rather than
 * as itself — a template is not a way to probe what the server knows.
 */
class EmailTemplateRenderer
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string}|null
     */
    public function render(NotificationEvent $event, array $variables): ?array
    {
        try {
            $template = EmailTemplate::query()
                ->enabled()
                ->where('event_key', $event->value)
                ->first();

            if (! $template instanceof EmailTemplate) {
                return null;
            }

            $rendered = $this->preview($template->subject, $template->body, $variables);

            // A template that renders to nothing is a broken template, not a
            // silent empty email.
            if ($rendered['subject'] === '' || trim(strip_tags($rendered['html'])) === '') {
                return null;
            }

            return $rendered;
        } catch (Throwable $e) {
            Log::warning('Email template failed to render; falling back to the shipped default.', [
                'event' => $event->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Render arbitrary (possibly unsaved) template source — what the admin
     * screen's preview shows, and the same path a real send takes, so what the
     * preview shows is what the recipient gets.
     *
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string}
     */
    public function preview(string $subject, string $body, array $variables): array
    {
        return [
            'subject' => trim($this->substitute($subject, $variables)),
            // Markdown, so raw HTML in an admin's body is stripped (D20) — a
            // template cannot smuggle a script tag into an email.
            'html' => $this->markdown->render($this->substitute($body, $variables)),
        ];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function substitute(string $text, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $match): string => $variables[strtolower($match[1])] ?? '',
            $text,
        );
    }
}
