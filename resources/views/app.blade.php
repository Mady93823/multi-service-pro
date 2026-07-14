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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
