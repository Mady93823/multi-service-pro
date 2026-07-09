<?php

namespace App\Http\Middleware;

use App\Domain\Bookings\CartManager;
use App\Domain\Localization\TranslationLoader;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = app(SettingsRegistry::class);
        $logoPath = $settings->string('branding.logo_path');

        return array_merge(parent::share($request), [
            'name' => $settings->string('branding.app_name', (string) config('app.name')),
            'translations' => app(TranslationLoader::class)->forLocale(app()->getLocale()),
            'branding' => [
                'logo_url' => $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null,
                'primary_color' => $settings->string('branding.primary_color') ?: null,
            ],
            'localization' => [
                'currency' => $settings->string('localization.currency', 'INR'),
                'locale' => $settings->string('localization.locale', 'en'),
                'timezone' => $settings->string('localization.timezone', 'Asia/Kolkata'),
            ],
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'cart' => [
                'count' => $request->hasSession() ? app(CartManager::class)->count() : 0,
            ],
            'notifications' => $this->notifications($request->user()),
        ]);
    }

    /**
     * Unread badge + a short recent list for the bell menu (M11). Lazily
     * shaped so guests and unauthenticated requests pay nothing.
     *
     * @return array{unread_count: int, recent: list<array<string, mixed>>}
     */
    private function notifications(?User $user): array
    {
        if ($user === null) {
            return ['unread_count' => 0, 'recent' => []];
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->limit(8)->get()
                ->map(fn (DatabaseNotification $n): array => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
