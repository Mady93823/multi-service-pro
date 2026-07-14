<?php

namespace App\Domain\Comms\Actions;

use App\Domain\Comms\Enums\NotificationEvent;
use App\Models\EmailTemplate;

/**
 * Create or update one event's email override (M23).
 *
 * There is exactly one row per event (`event_key` is unique), so saving is an
 * upsert: an admin edits *the* template for an event, never one of several.
 */
class SaveEmailTemplate
{
    /**
     * @param  array{subject: string, body: string, is_enabled?: bool}  $data
     */
    public function handle(NotificationEvent $event, array $data): EmailTemplate
    {
        /** @var EmailTemplate $template */
        $template = EmailTemplate::query()->updateOrCreate(
            ['event_key' => $event->value],
            [
                'subject' => $data['subject'],
                'body' => $data['body'],
                'is_enabled' => $data['is_enabled'] ?? true,
            ],
        );

        return $template;
    }
}
