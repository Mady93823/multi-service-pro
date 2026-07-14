<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * What the browser needs in order to open a WebSocket, resolved **per request**.
 *
 * This exists because of a bug that only a real install could have shown us
 * (P7.3). `configureEcho()` was reading `import.meta.env.VITE_REVERB_APP_KEY`,
 * which is baked into `public/build` at compile time — and we ship the bundle
 * prebuilt, while the installer generates a **fresh random `REVERB_APP_KEY` for
 * every install**. So every buyer's JavaScript would have carried *our* key,
 * Reverb would have rejected every connection, and live tracking and the
 * notification bell would have failed silently on a site that otherwise looked
 * perfectly healthy. A build-time constant cannot describe a runtime install.
 *
 * The secret and the app id stay on the server. Only the public key, the host
 * the browser should dial, its port and its scheme go out — which is exactly
 * what a Pusher-protocol client is supposed to know.
 */
final class ReverbConfig
{
    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    public static function forBrowser(Request $request): array
    {
        /** @var array<string, mixed> $options */
        $options = config('broadcasting.connections.reverb.options', []);

        $scheme = is_string($options['scheme'] ?? null) && $options['scheme'] !== ''
            ? $options['scheme']
            : ($request->isSecure() ? 'https' : 'http');

        // An empty REVERB_HOST means "same host as the site" — the common case
        // behind a reverse proxy, and the one a buyer never has to think about.
        $host = is_string($options['host'] ?? null) && $options['host'] !== ''
            ? $options['host']
            : $request->getHost();

        return [
            'key' => (string) config('broadcasting.connections.reverb.key', ''),
            'host' => $host,
            'port' => (int) ($options['port'] ?? ($scheme === 'https' ? 443 : 8080)),
            'scheme' => $scheme,
        ];
    }
}
