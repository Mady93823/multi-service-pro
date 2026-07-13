<?php

namespace App\Domain\Settings\Groups;

use App\Models\Page;

class CookieGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'cookie';
    }

    public function label(): string
    {
        return __('Cookie & GDPR');
    }

    public function description(): string
    {
        return __('The consent banner shown to storefront visitors. Off by default.');
    }

    public function keys(): array
    {
        return ['cookie.enabled', 'cookie.message', 'cookie.accept_label', 'cookie.decline_label', 'cookie.policy_slug'];
    }

    public function rules(array $input): array
    {
        return [
            'enabled' => ['boolean'],
            'message' => ['required_if:enabled,true', 'nullable', 'string', 'max:500'],
            'accept_label' => ['required_if:enabled,true', 'nullable', 'string', 'max:40'],
            'decline_label' => ['nullable', 'string', 'max:40'],
            // A CMS page slug, not a URL: the privacy page already lives at /p/{slug}.
            'policy_slug' => ['nullable', 'string', 'max:120', 'exists:pages,slug'],
        ];
    }

    public function values(): array
    {
        return [
            'enabled' => $this->settings->boolean('cookie.enabled'),
            'message' => $this->settings->string('cookie.message') ?: null,
            'accept_label' => $this->settings->string('cookie.accept_label') ?: null,
            'decline_label' => $this->settings->string('cookie.decline_label') ?: null,
            'policy_slug' => $this->settings->string('cookie.policy_slug') ?: null,
            // Not a setting — the choices the policy-page picker offers. values()
            // shapes a screen; only keys() decides what may be written.
            'pages' => Page::query()
                ->where('is_published', true)
                ->orderBy('title')
                ->get(['title', 'slug'])
                ->map(fn (Page $page): array => ['value' => $page->slug, 'label' => $page->title])
                ->all(),
        ];
    }

    public function apply(array $data, array $files = []): void
    {
        $this->settings->set('cookie.enabled', $this->toggle($data, 'enabled'));

        foreach (['message', 'accept_label', 'decline_label', 'policy_slug'] as $field) {
            $this->settings->set('cookie.'.$field, $data[$field] ?? null);
        }
    }
}
