<?php

namespace App\Domain\System\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs `app:update` from the browser (M24).
 *
 * It is the same command an operator with SSH would type — one update path, not
 * two, for the same reason there is one money path (D27). The output comes back
 * so a failed migration is read on the screen that started it, rather than
 * guessed at from a white page.
 */
class RunUpdate
{
    public function handle(): string
    {
        Artisan::call('app:update');

        return trim(Artisan::output());
    }
}
