<?php

namespace App\Domain\Settings;

use App\Domain\Settings\Groups\AnalyticsGroup;
use App\Domain\Settings\Groups\AppearanceGroup;
use App\Domain\Settings\Groups\BlogGroup;
use App\Domain\Settings\Groups\BookingGroup;
use App\Domain\Settings\Groups\BrandingGroup;
use App\Domain\Settings\Groups\CookieGroup;
use App\Domain\Settings\Groups\CurrencyGroup;
use App\Domain\Settings\Groups\CustomCodeGroup;
use App\Domain\Settings\Groups\DispatchGroup;
use App\Domain\Settings\Groups\FeaturesGroup;
use App\Domain\Settings\Groups\IntegrationsGroup;
use App\Domain\Settings\Groups\InvoiceGroup;
use App\Domain\Settings\Groups\LocalizationGroup;
use App\Domain\Settings\Groups\MailGroup;
use App\Domain\Settings\Groups\PaymentsGroup;
use App\Domain\Settings\Groups\PayoutsGroup;
use App\Domain\Settings\Groups\RecaptchaGroup;
use App\Domain\Settings\Groups\ReferralsGroup;
use App\Domain\Settings\Groups\ReviewsGroup;
use App\Domain\Settings\Groups\SeoGroup;
use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\Groups\SmsGroup;
use App\Domain\Settings\Groups\SocialGroup;
use App\Domain\Settings\Groups\SupportGroup;
use App\Domain\Settings\Groups\TrackingGroup;

/**
 * The settings screens, in sidebar order (ADR D24).
 *
 * Adding a settings key means adding it to exactly one group — the coverage
 * test fails otherwise, which is deliberately louder than the silent 422 the
 * old single-payload request produced.
 */
class SettingsGroupRegistry
{
    /** @var list<class-string<SettingsGroup>> */
    private const GROUPS = [
        BrandingGroup::class,
        AppearanceGroup::class,
        SocialGroup::class,
        LocalizationGroup::class,
        CurrencyGroup::class,
        SeoGroup::class,
        AnalyticsGroup::class,
        FeaturesGroup::class,
        BookingGroup::class,
        DispatchGroup::class,
        TrackingGroup::class,
        PaymentsGroup::class,
        PayoutsGroup::class,
        InvoiceGroup::class,
        ReviewsGroup::class,
        ReferralsGroup::class,
        SupportGroup::class,
        MailGroup::class,
        SmsGroup::class,
        IntegrationsGroup::class,
        RecaptchaGroup::class,
        BlogGroup::class,
        CookieGroup::class,
        CustomCodeGroup::class,
    ];

    /** @var array<string, SettingsGroup>|null */
    private ?array $resolved = null;

    /**
     * @return array<string, SettingsGroup>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $groups = [];

        foreach (self::GROUPS as $class) {
            $group = app($class);
            $groups[$group->key()] = $group;
        }

        return $this->resolved = $groups;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): ?SettingsGroup
    {
        return $this->all()[$key] ?? null;
    }

    public function default(): SettingsGroup
    {
        return array_values($this->all())[0];
    }

    /**
     * Nav entries for the settings sub-navigation.
     *
     * @return list<array{key: string, label: string}>
     */
    public function navigation(): array
    {
        return array_values(array_map(
            fn (SettingsGroup $group): array => ['key' => $group->key(), 'label' => $group->label()],
            $this->all(),
        ));
    }
}
