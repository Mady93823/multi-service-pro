<?php

namespace App\Domain\Marketing\Actions;

use App\Models\Subscriber;

/**
 * Newsletter signup (M19). Idempotent by email: signing up twice is not an
 * error, and signing up again after unsubscribing re-subscribes the same row —
 * the address is the identity, and the row is what remembers the opt-out.
 */
class Subscribe
{
    public function handle(string $email, string $source = 'footer'): Subscriber
    {
        $subscriber = Subscriber::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);

        $subscriber->source = $source;
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        return $subscriber;
    }
}
