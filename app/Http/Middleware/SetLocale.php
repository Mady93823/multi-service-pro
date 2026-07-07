<?php

namespace App\Http\Middleware;

use App\Domain\Settings\SettingsRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private SettingsRegistry $settings) {}

    /**
     * Apply the admin-configured locale to the application.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->settings->string('localization.locale', (string) config('app.locale'));

        if (preg_match('/^[a-z]{2}([_-][A-Za-z]{2,4})?$/', $locale) === 1) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
