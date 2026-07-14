<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Comms\Actions\SaveNotificationPreferences;
use App\Domain\Comms\Actions\SendAnnouncement;
use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\MailConfigurator;
use App\Domain\Comms\NotificationPreferences;
use App\Domain\Comms\SmsManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveNotificationMatrixRequest;
use App\Http\Requests\Admin\SendAnnouncementRequest;
use App\Notifications\FcmChannel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The event × channel matrix and the announcement composer (M23).
 *
 * The screen shows which channels are actually *available* — an install with no
 * SMTP can switch email on all it likes and nothing will send (D14), so it says
 * so instead of lying.
 */
class NotificationController extends Controller
{
    public function index(
        NotificationPreferences $preferences,
        MailConfigurator $mail,
        SmsManager $sms,
    ): Response {
        return Inertia::render('admin/notifications/index', [
            'events' => array_map(fn (NotificationEvent $event): array => [
                'key' => $event->value,
                'label' => $event->label(),
                'description' => $event->description(),
            ], NotificationEvent::all()),
            'channels' => array_map(fn (NotificationChannel $channel): array => [
                'key' => $channel->value,
                'label' => $channel->label(),
                'available' => match ($channel) {
                    NotificationChannel::Mail => $mail->isConfigured(),
                    NotificationChannel::Sms => $sms->isConfigured(),
                    NotificationChannel::Fcm => FcmChannel::isConfigured(),
                },
            ], NotificationChannel::all()),
            'matrix' => $preferences->matrix(),
            'segments' => SendAnnouncement::SEGMENTS,
        ]);
    }

    public function update(
        SaveNotificationMatrixRequest $request,
        SaveNotificationPreferences $action,
        ActivityLogger $activity,
    ): RedirectResponse {
        $action->handle(null, $request->preferences());

        $activity->log($request->user(), 'notifications.matrix', null, [
            'rows' => count($request->preferences()),
        ]);

        return back()->with('success', __('Notification settings saved.'));
    }

    public function announce(
        SendAnnouncementRequest $request,
        SendAnnouncement $action,
        ActivityLogger $activity,
    ): RedirectResponse {
        $sent = $action->handle(
            (string) $request->string('segment'),
            (string) $request->string('title'),
            (string) $request->string('message'),
            (string) $request->string('url'),
        );

        $activity->log($request->user(), 'notifications.announce', null, [
            'segment' => (string) $request->string('segment'),
            'recipients' => $sent,
        ]);

        return back()->with('success', __(':count people were notified.', ['count' => $sent]));
    }
}
