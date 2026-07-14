<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response headers a browser needs in order to defend a user we cannot.
 *
 * These are cheap, they apply to every route, and each one closes a hole that
 * no policy check can:
 *
 * - `nosniff` stops a stored file from being re-interpreted as HTML or a script
 *   because the browser disagreed with our Content-Type.
 * - `X-Frame-Options: DENY` makes the admin panel unframeable, so a click on an
 *   attacker's page cannot be a click on "Refund" or "Approve payout". Nothing
 *   in this app frames itself; the embed block (M20) frames *other* sites, which
 *   this does not touch.
 * - `Permissions-Policy` withholds every device API except the one the product
 *   actually needs: geolocation, same-origin only, because the provider journey
 *   screen (M07) reads GPS. Dropping it here would silently kill live tracking.
 * - HSTS goes out only over a real HTTPS request in production. Sending it in
 *   development would pin `localhost` to https in the developer's browser and
 *   there is no way to unstick it from the app.
 *
 * No CSP yet, deliberately: D26 lets an admin paste their own JS into the
 * storefront and M24 loads analytics by ID, so any policy we could ship today
 * would need `unsafe-inline` and would prove nothing. That is a Phase 7 item of
 * its own, not a header to fake here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // PHP advertises its version by default; it is free reconnaissance.
        header_remove('X-Powered-By');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',
        ];

        if ($request->secure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // A route that set one on purpose (a file download's own disposition
            // rules, say) keeps it — this fills gaps, it does not overrule.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
