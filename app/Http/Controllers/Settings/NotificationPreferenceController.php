<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Comms\Actions\SaveNotificationPreferences;
use App\Domain\Comms\Enums\NotificationChannel;
use App\Domain\Comms\Enums\NotificationEvent;
use App\Domain\Comms\MailConfigurator;
use App\Domain\Comms\NotificationPreferences;
use App\Domain\Comms\SmsManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\User;
use App\Notifications\FcmChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A user's own opt-outs (M23). They win over the admin's defaults — an opt-out
 * is a promise, not a suggestion.
 *
 * The in-app feed and the live bell are not on this screen: they are the
 * platform's record of what it did to your booking and your money, and a user
 * who switched them off would have no way to see either.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(
        Request $request,
        NotificationPreferences $preferences,
        MailConfigurator $mail,
        SmsManager $sms,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/notifications', [
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
                    NotificationChannel::Sms => $sms->isConfigured() && (string) $user->phone !== '',
                    NotificationChannel::Fcm => FcmChannel::isConfigured(),
                },
            ], NotificationChannel::all()),
            'matrix' => $preferences->forUser($user),
        ]);
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        SaveNotificationPreferences $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $request->preferences());

        return back()->with('success', __('Notification settings saved.'));
    }
}
