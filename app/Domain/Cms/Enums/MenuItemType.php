<?php

namespace App\Domain\Cms\Enums;

enum MenuItemType: string
{
    /** A named storefront route, from the allowlist below. */
    case Route = 'route';

    /** A CMS page, by slug — rendered as /p/{slug}. */
    case Page = 'page';

    /** An absolute http(s) URL, or a site-relative path. */
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Route => __('Site page'),
            self::Page => __('CMS page'),
            self::Url => __('Custom link'),
        };
    }

    /**
     * The only route names a menu item may point at. An admin-supplied route
     * name is a `route()` call on user input: an unknown name throws, and a
     * name outside the storefront (an admin route, a POST-only endpoint) would
     * put a link in the header that no visitor can follow. Hence an allowlist,
     * not a lookup over Route::getRoutes().
     *
     * @return array<string, string> route name => label
     */
    public static function allowedRoutes(): array
    {
        return [
            'home' => __('Home'),
            'catalog.index' => __('Services'),
            'cart.show' => __('Cart'),
            'dashboard' => __('Customer dashboard'),
            'bookings.index' => __('My bookings'),
            'support.tickets.index' => __('Help'),
            'login' => __('Log in'),
            'register' => __('Sign up'),
        ];
    }
}
