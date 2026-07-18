<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $settings = app(\App\Domain\Settings\SettingsRegistry::class);
            $primaryColor = $settings->string('branding.primary_color');
            $faviconPath = $settings->string('branding.favicon_path');

            // The uploaded icon, or the mark generated from the app's initial and
            // the brand colour. Never a static file of ours (D8, white-label).
            $faviconUrl = $faviconPath !== ''
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath)
                : route('favicon');
        @endphp

        <title inertia>{{ $settings->string('branding.app_name', (string) config('app.name')) }}</title>

        <link rel="icon" href="{{ $faviconUrl }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">

        @if (\App\Support\BrandMark::isValidColor($primaryColor))
            {{-- Painted here as well as in `BrandTheme`, so the first frame is already
                 the buyer's colour: a React effect runs after paint, and a site that
                 flashes indigo on every load looks broken, not branded. --}}
            <style>
                :root, .dark {
                    --primary: {{ $primaryColor }};
                    --primary-foreground: {{ \App\Support\BrandMark::foregroundFor($primaryColor) }};
                    --ring: {{ $primaryColor }};
                    --sidebar-primary: {{ $primaryColor }};
                    --sidebar-primary-foreground: {{ \App\Support\BrandMark::foregroundFor($primaryColor) }};
                    --sidebar-ring: {{ $primaryColor }};
                }
            </style>
        @endif

        {{-- Splash shown while the JS bundle downloads (app.tsx removes it after
             mount). Colour rides `--primary`, so the branding override above and
             the buyer's theme both reach it — never a hardcoded hue (D8). --}}
        <style>
            #splash {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 1.5rem;
                background: var(--background, #fff);
                transition: opacity 0.3s ease;
            }
            #splash.splash-done {
                opacity: 0;
                pointer-events: none;
            }
            #splash .splash-mark {
                position: relative;
                width: 72px;
                height: 22px;
            }
            #splash .splash-mark i {
                position: absolute;
                right: 0;
                top: 50%;
                width: 16px;
                height: 16px;
                margin-top: -8px;
                border-radius: 9999px;
                background: var(--primary, #4f46e5);
                animation: splash-bob 1s ease-in-out infinite;
            }
            #splash .splash-mark span {
                position: absolute;
                left: 0;
                height: 3px;
                border-radius: 9999px;
                background: var(--primary, #4f46e5);
                animation: splash-dash 1s ease-in-out infinite;
            }
            #splash .splash-mark span:nth-child(1) { top: 1px; width: 30px; }
            #splash .splash-mark span:nth-child(2) { top: 50%; margin-top: -1.5px; width: 44px; animation-delay: 0.15s; }
            #splash .splash-mark span:nth-child(3) { bottom: 1px; width: 22px; animation-delay: 0.3s; }
            #splash p {
                margin: 0;
                font-size: 11px;
                font-weight: 500;
                letter-spacing: 0.35em;
                text-transform: uppercase;
                color: var(--primary, #4f46e5);
            }
            @keyframes splash-dash {
                0%, 100% { transform: translateX(0); opacity: 0.25; }
                50% { transform: translateX(12px); opacity: 0.85; }
            }
            @keyframes splash-bob {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-3px); }
            }
            @media (prefers-reduced-motion: reduce) {
                #splash .splash-mark i,
                #splash .splash-mark span { animation: none; }
            }
        </style>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="splash" aria-hidden="true">
            <div class="splash-mark">
                <span></span><span></span><span></span><i></i>
            </div>
            <p>{{ __('Loading...') }}</p>
        </div>
        @inertia
    </body>
</html>
