<?php

namespace App\Domain\Comms\Actions;

use App\Models\EmailTemplate;

/**
 * Drop an override (M23). Nothing stops sending — the notification simply goes
 * back to its shipped default (D25), which is the whole point of the layer.
 */
class DeleteEmailTemplate
{
    public function handle(EmailTemplate $template): void
    {
        $template->delete();
    }
}
