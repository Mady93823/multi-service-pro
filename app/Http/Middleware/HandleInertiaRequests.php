<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Actions\StartImpersonation;
use App\Domain\Bookings\CartManager;
use App\Domain\Cities\ActiveCity;
use App\Domain\Cms\FooterPages;
use App\Domain\Cms\SiteContent;
use App\Domain\Localization\TranslationLoader;
use App\Domain\Security\Recaptcha;
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
                // M24 (D23): format only — one currency per install, no conversion.
                // The browser prints money exactly the way App\Support\Money does.
                'symbol' => $settings->string('currency.symbol', '₹'),
                'position' => $settings->string('currency.position', 'before'),
                'decimals' => $settings->integer('currency.decimals', 2),
                'grouping' => $settings->string('currency.grouping', 'indian'),
            ],
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                // M24: the `app:update` console output, read on the screen that started it.
                'update_output' => $request->session()->get('update_output'),
            ],
            'cart' => [
                'count' => $request->hasSession() ? app(CartManager::class)->count() : 0,
            ],
            'notifications' => $this->notifications($request->user()),
            'impersonation' => $this->impersonation($request),
            // M14: white-label footer links — cached, flushed on page save.
            'footer_pages' => app(FooterPages::class)->all(),
            // M19: menus, header/footer style, social, cookie banner, custom code.
            // M24 adds analytics (storefront only, consent-gated).
            // M25 adds the city switcher — the session holds the visitor's choice.
            'site' => app(SiteContent::class)->share(
                $request->user(),
                $this->isStorefront($request),
                $request->hasSession() ? $this->sessionCityId($request) : null,
            ),
            // M24: only the *site* key ever crosses to the browser — it is public
            // by design — and only when reCaptcha is configured AND a form uses it.
            // Null on a fresh install, so no script loads and no form waits on one.
            'recaptcha' => app(Recaptcha::class)->share(),
        ]);
    }

    private function sessionCityId(Request $request): ?int
    {
        $chosen = $request->session()->get(ActiveCity::SESSION_KEY);

        return is_numeric($chosen) ? (int) $chosen : null;
    }

    /**
     * The public site, as opposed to the admin / provider panels or the
     * installer. Custom CSS/JS is shared with the storefront only (D26).
     */
    private function isStorefront(Request $request): bool
    {
        return ! $request->is('admin', 'admin/*', 'provider', 'provider/*', 'install', 'install/*');
    }

    /**
     * Non-null while an admin is browsing as someone else (M13) — every shell
     * renders the warning banner + leave control off this prop.
     *
     * @return array{user_name: string}|null
     */
    private function impersonation(Request $request): ?array
    {
        if (! $request->hasSession()
            || ! $request->session()->has(StartImpersonation::SESSION_KEY)) {
            return null;
        }

        // The session key only ever coexists with an authenticated user —
        // impersonation starts logged in, and logout invalidates the session.
        return ['user_name' => $request->user()->name];
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
