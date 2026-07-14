<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Comms\Actions\DeleteEmailTemplate;
use App\Domain\Comms\Actions\SaveEmailTemplate;
use App\Domain\Comms\Actions\SendTestEmail;
use App\Domain\Comms\EmailTemplateRenderer;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\MailConfigurator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewEmailTemplateRequest;
use App\Http\Requests\Admin\SaveEmailTemplateRequest;
use App\Http\Requests\Admin\SendTestEmailRequest;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The optional layer over every shipped email (ADR D25). A missing row here is
 * not a broken feature — it is the default.
 */
class EmailTemplateController extends Controller
{
    public function index(MailConfigurator $mail): Response
    {
        $templates = EmailTemplate::query()->get()->keyBy('event_key');

        return Inertia::render('admin/email-templates/index', [
            'events' => array_map(fn (NotificationEvent $event): array => [
                'key' => $event->value,
                'label' => $event->label(),
                'description' => $event->description(),
                'has_template' => $templates->has($event->value),
                'is_enabled' => $templates->get($event->value)?->is_enabled === true,
            ], NotificationEvent::all()),
            'mail_configured' => $mail->isConfigured(),
        ]);
    }

    public function edit(string $event, MailConfigurator $mail): Response
    {
        $notificationEvent = $this->event($event);
        $template = $this->template($notificationEvent);

        return Inertia::render('admin/email-templates/edit', [
            'event' => [
                'key' => $notificationEvent->value,
                'label' => $notificationEvent->label(),
                'description' => $notificationEvent->description(),
                'variables' => $notificationEvent->variables(),
            ],
            'template' => $template === null ? null : [
                'subject' => $template->subject,
                'body' => $template->body,
                'is_enabled' => $template->is_enabled,
            ],
            'mail_configured' => $mail->isConfigured(),
        ]);
    }

    public function update(
        SaveEmailTemplateRequest $request,
        string $event,
        SaveEmailTemplate $action,
        ActivityLogger $activity,
    ): RedirectResponse {
        $notificationEvent = $this->event($event);

        /** @var array{subject: string, body: string, is_enabled?: bool} $data */
        $data = $request->validated();

        $action->handle($notificationEvent, $data);

        $activity->log($request->user(), 'email_template.save', null, ['event' => $notificationEvent->value]);

        return back()->with('success', __('Template saved.'));
    }

    public function destroy(string $event, DeleteEmailTemplate $action, ActivityLogger $activity): RedirectResponse
    {
        $notificationEvent = $this->event($event);
        $template = $this->template($notificationEvent);

        if ($template !== null) {
            $action->handle($template);

            $activity->log(request()->user(), 'email_template.delete', null, ['event' => $notificationEvent->value]);
        }

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', __('Template removed — the built-in email is used again.'));
    }

    /**
     * The preview talks JSON, not Inertia: it fires while the admin is typing
     * and an Inertia visit would throw the half-written template away (M18).
     */
    public function preview(PreviewEmailTemplateRequest $request, string $event, EmailTemplateRenderer $renderer): JsonResponse
    {
        $notificationEvent = $this->event($event);

        return response()->json($renderer->preview(
            (string) $request->string('subject'),
            (string) $request->string('body'),
            $notificationEvent->sample(),
        ));
    }

    public function test(SendTestEmailRequest $request, string $event, SendTestEmail $action): RedirectResponse
    {
        $action->handle((string) $request->string('email'), $this->event($event));

        return back()->with('success', __('Test email sent.'));
    }

    private function event(string $event): NotificationEvent
    {
        $notificationEvent = NotificationEvent::tryFrom($event);

        abort_if($notificationEvent === null, 404);

        return $notificationEvent;
    }

    private function template(NotificationEvent $event): ?EmailTemplate
    {
        return EmailTemplate::query()->where('event_key', $event->value)->first();
    }
}
