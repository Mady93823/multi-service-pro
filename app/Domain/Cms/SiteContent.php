<?php

namespace App\Domain\Cms;

use App\Domain\Settings\Groups\SocialGroup;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Popup;
use App\Models\User;
use Throwable;

/**
 * Everything the storefront shell needs to draw itself (M19): menus, header and
 * footer style, footer contact block, social links, cookie banner, custom code.
 *
 * One read model so the Inertia middleware stays a shape, not a query.
 */
class SiteContent
{
    public function __construct(
        private SettingsRegistry $settings,
        private SiteMenus $menus,
        private MarkdownRenderer $markdown,
    ) {}

    /**
     * @param  bool  $storefront  false inside the admin / provider panels
     * @return array<string, mixed>
     */
    public function share(?User $user, bool $storefront): array
    {
        return [
            'menus' => $this->menus->forUser($user),
            'appearance' => [
                'header_variant' => $this->settings->string('appearance.header_variant', 'classic'),
                'sticky_header' => $this->settings->boolean('appearance.sticky_header', true),
                'footer_variant' => $this->settings->string('appearance.footer_variant', 'columns'),
                'footer_about' => $this->settings->string('appearance.footer_about') ?: null,
                'copyright' => $this->settings->string('appearance.copyright') ?: null,
                'contact_email' => $this->settings->string('appearance.contact_email') ?: null,
                'contact_phone' => $this->settings->string('appearance.contact_phone') ?: null,
                'contact_address' => $this->settings->string('appearance.contact_address') ?: null,
                'login_headline' => $this->settings->string('appearance.login_headline') ?: null,
                'login_subcopy' => $this->settings->string('appearance.login_subcopy') ?: null,
                'login_image_url' => $this->settings->string('appearance.login_image_url') ?: null,
            ],
            'social' => $this->social(),
            'cookie' => $this->cookie(),
            'custom_code' => $this->customCode($storefront),
            'newsletter' => $this->settings->boolean('appearance.newsletter_enabled', true),
            'popup' => $storefront ? $this->popup($user) : null,
        ];
    }

    /**
     * The live popup for this visitor, if any (M19). Audience is decided on the
     * server; the browser only decides *how often* it has already seen it.
     *
     * @return array<string, mixed>|null
     */
    private function popup(?User $user): ?array
    {
        try {
            $popups = Popup::query()->live()->with('media')->orderByDesc('id')->get();
        } catch (Throwable) {
            // Table missing (installer, early boot).
            return null;
        }

        foreach ($popups as $popup) {
            if (! $popup->audience->allows($user)) {
                continue;
            }

            $image = $popup->getFirstMedia('image');

            return [
                'id' => $popup->id,
                'title' => $popup->title,
                // Markdown → sanitized HTML through the one renderer (D20).
                'html' => $popup->body === null ? null : $this->markdown->render($popup->body),
                'link_url' => $popup->link_url,
                'link_label' => $popup->link_label,
                'image_url' => $image?->getUrl('card'),
                'frequency_days' => $popup->frequency_days,
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function social(): array
    {
        $links = [];

        foreach (SocialGroup::NETWORKS as $network) {
            $url = $this->settings->string('social.'.$network);

            if ($url !== '') {
                $links[$network] = $url;
            }
        }

        return $links;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cookie(): ?array
    {
        if (! $this->settings->boolean('cookie.enabled')) {
            return null;
        }

        return [
            'message' => $this->settings->string('cookie.message'),
            'accept_label' => $this->settings->string('cookie.accept_label', __('Accept')),
            'decline_label' => $this->settings->string('cookie.decline_label') ?: null,
            'policy_slug' => $this->settings->string('cookie.policy_slug') ?: null,
        ];
    }

    /**
     * Admin-authored CSS/JS, and only on the storefront (D26). The admin panel
     * never receives it: a snippet that breaks the page must never break the
     * screen an admin needs in order to remove it.
     *
     * @return array{css: ?string, js: ?string}|null
     */
    private function customCode(bool $storefront): ?array
    {
        if (! $storefront || ! $this->settings->boolean('custom_code.enabled')) {
            return null;
        }

        $css = $this->settings->string('custom_code.css') ?: null;
        $js = $this->settings->string('custom_code.js') ?: null;

        if ($css === null && $js === null) {
            return null;
        }

        return ['css' => $css, 'js' => $js];
    }
}
