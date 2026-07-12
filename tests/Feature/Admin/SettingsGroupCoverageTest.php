<?php

use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\SettingsGroupRegistry;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;

/**
 * The guard that makes ADR D24 hold: a settings key is editable in exactly one
 * place. Without it, adding a key to the registry and forgetting the group
 * would leave a setting no admin can ever change — the silent failure the old
 * single-payload request traded for a noisy 422.
 */
test('every settings key is owned by exactly one group', function () {
    $owners = [];

    foreach (app(SettingsGroupRegistry::class)->all() as $group) {
        foreach ($group->keys() as $key) {
            $owners[$key][] = $group->key();
        }
    }

    $duplicated = array_keys(array_filter($owners, fn (array $groups): bool => count($groups) > 1));
    $known = array_keys(SettingsRegistry::defaults());
    $unowned = array_values(array_diff($known, array_keys($owners)));
    $unknown = array_values(array_diff(array_keys($owners), $known));

    expect($duplicated)->toBe([], 'These keys are claimed by more than one settings group.')
        ->and($unowned)->toBe([], 'These settings keys have no group — no admin can edit them.')
        ->and($unknown)->toBe([], 'These groups claim keys that are not in SettingsRegistry::defaults().');
});

test('every group exposes a value for each of its own keys and a working screen', function () {
    $admin = User::factory()->admin()->create();

    foreach (app(SettingsGroupRegistry::class)->all() as $group) {
        expect($group)->toBeInstanceOf(SettingsGroup::class)
            ->and($group->label())->not->toBe('')
            ->and($group->keys())->not->toBe([]);

        $this->actingAs($admin)
            ->get("/admin/settings/{$group->key()}")
            ->assertOk();
    }
});
