<?php

namespace App\Domain\Marketing\Actions;

use App\Models\Subscriber;

class Unsubscribe
{
    /**
     * The row survives: a deleted subscriber would be silently re-added by the
     * next signup form submission, losing the fact that they opted out.
     */
    public function handle(Subscriber $subscriber): void
    {
        $subscriber->update(['unsubscribed_at' => now()]);
    }
}
