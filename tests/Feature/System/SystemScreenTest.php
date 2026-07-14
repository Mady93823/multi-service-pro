<?php

use App\Domain\Settings\SettingsRegistry;
use App\Domain\System\Actions\RunUpdate;
use App\Domain\System\ScheduleStatus;
use App\Models\ActivityLog;
use App\Models\User;
use Tests\Support\SettingsFixtures;

/**
 * M24: the System screen exists to catch the most common broken install — a
 * scheduler nobody wired up — and to separate "broken" from "not configured".
 */
test('a scheduler that has never run is reported as stale', function () {
    $status = app(ScheduleStatus::class);

    expect($status->lastRun())->toBeNull()
        ->and($status->isStale())->toBeTrue();

    $this->artisan('system:heartbeat')->assertSuccessful();

    expect(app(ScheduleStatus::class)->isStale())->toBeFalse()
        ->and(app(SettingsRegistry::class)->string(ScheduleStatus::LAST_RUN_KEY))->not->toBe('');
});

test('an old heartbeat is stale again', function () {
    app(SettingsRegistry::class)->set(ScheduleStatus::LAST_RUN_KEY, now()->subDay()->toIso8601String());

    expect(app(ScheduleStatus::class)->isStale())->toBeTrue();
});

test('the scheduled task list carries the crontab line an operator has to paste', function () {
    $status = app(ScheduleStatus::class);

    expect($status->cronLine())->toContain('schedule:run')
        ->and($status->tasks())->not->toBeEmpty();
});

test('the system screen renders for an admin and nobody else', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system/index')
            ->has('checks')
            ->has('about.version')
            ->where('scheduler.is_stale', true));

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.system.index'))
        ->assertForbidden();
});

test('an unconfigured integration is reported as off, never as an error', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.system.index'))->assertOk();

    /** @var array<int, array<string, string>> $checks */
    $checks = $response->viewData('page')['props']['checks'];

    $optional = collect($checks)->whereIn('key', ['mail', 'sms', 'push']);

    // A fresh install has none of these. If any of them were red, an operator
    // would learn to ignore the whole page.
    expect($optional)->toHaveCount(3)
        ->and($optional->pluck('status')->unique()->all())->toBe(['ok']);
});

test('running the update from the browser is audited', function () {
    // The real action runs `optimize:clear`, which deletes
    // `bootstrap/cache/packages.php` — and bootstrap/cache is REAL, SHARED state
    // across the parallel test workers, exactly like `lang/`. Whichever worker
    // happened to be booting at that moment then raced the others to rebuild the
    // manifest and died on Windows with
    // `rename(...packages.php): Access is denied`. The failure surfaced on a
    // random innocent test (usually one of the Zones ones) roughly one run in
    // three, and read as a zone bug. Swapped: the route's contract is "run the
    // update, then audit it", and that is what this proves. `RunUpdate` itself is
    // a thin artisan sequence.
    $this->mock(RunUpdate::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn('Update complete.');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.system.update'))
        ->assertRedirect()
        ->assertSessionHas('update_output');

    expect(ActivityLog::query()->where('action', 'system.update')->exists())->toBeTrue();
});

test('a customer cannot run the update', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->post(route('admin.system.update'))
        ->assertForbidden();

    expect(ActivityLog::query()->where('action', 'system.update')->exists())->toBeFalse();
});

test('the API keys screen keeps its secrets write-only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'integrations'), SettingsFixtures::payload('integrations', [
            'fcm_credentials' => '{"type":"service_account"}',
            'google_maps_key' => 'maps-key',
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->string('integrations.google_maps_key'))->toBe('maps-key');

    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'integrations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('values.google_maps_key_set', true)
            ->missing('values.google_maps_key'))
        ->assertDontSee('maps-key');
});

test('a truncated Firebase paste is caught on the form, not at the first push', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'integrations'), SettingsFixtures::payload('integrations', [
            'fcm_credentials' => '{"type":"service_acc',
        ]))
        ->assertSessionHasErrors('fcm_credentials');
});
