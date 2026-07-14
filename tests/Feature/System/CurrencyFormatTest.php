<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use App\Support\Money;
use Tests\Support\SettingsFixtures;

/**
 * ADR D23: one currency per install, and this screen is FORMAT — never a
 * conversion. A booking's money columns are snapshots; changing the symbol
 * changes how they print, not what they are worth.
 */
test('the shipped default prints Indian rupees with Indian grouping', function () {
    expect(Money::format(123456.5))->toBe('₹1,23,456.50')
        ->and(Money::format(-190))->toBe('-₹190.00');
});

test('symbol, position, decimals and grouping all come from settings', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('localization.currency', 'USD');
    $settings->set('currency.symbol', '$');
    $settings->set('currency.position', 'after');
    $settings->set('currency.decimals', 0);
    $settings->set('currency.grouping', 'western');

    expect(Money::format(123456.5))->toBe('123,457$');
});

test('a decimal column is never formatted — it stays a fixed-point string', function () {
    app(SettingsRegistry::class)->set('currency.symbol', '$');

    // Money::decimal writes DB columns. If settings ever leaked into it, every
    // amount in the database would be corrupted.
    expect(Money::decimal(1234.5))->toBe('1234.50');
});

test('the browser is handed the same format rules the server uses', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('currency.symbol', '$');
    $settings->set('currency.position', 'after');
    $settings->set('currency.grouping', 'western');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('localization.symbol', '$')
            ->where('localization.position', 'after')
            ->where('localization.grouping', 'western'));
});

test('an admin edits the currency on its own screen', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'currency'), SettingsFixtures::payload('currency', [
            'code' => 'AED',
            'symbol' => 'د.إ',
            'position' => 'after',
            'decimals' => 2,
            'grouping' => 'western',
        ]))
        ->assertRedirect();

    $settings = app(SettingsRegistry::class);

    // The code lives in localization.currency but is owned by the Currency
    // screen (D24: a screen group is not a storage group).
    expect($settings->string('localization.currency'))->toBe('AED')
        ->and($settings->string('currency.symbol'))->toBe('د.إ');
});

test('an unknown grouping or position is refused', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'currency'), SettingsFixtures::payload('currency', [
            'grouping' => 'martian',
            'position' => 'sideways',
        ]))
        ->assertSessionHasErrors(['grouping', 'position']);
});
