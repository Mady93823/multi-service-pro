<?php

use App\Domain\Installer\DeploymentGuide;
use App\Domain\Installer\InstallLock;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\System\ScheduleStatus;
use App\Jobs\QueueHeartbeat;
use App\Models\Language;
use App\Models\Page;
use App\Models\User;
use App\Support\ReverbConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

/**
 * P7.3 — the things only a fresh install finds.
 *
 * Every test here guards a failure that leaves the site *looking* healthy: a
 * WebSocket key that cannot match, a page that loads with no worker behind it,
 * a settings row the whole language system hangs off.
 */
test('the browser gets the reverb key from the response, never from the bundle', function () {
    // The bug this replaced: `configureEcho()` read VITE_REVERB_APP_KEY, which is
    // compiled into public/build — and the installer mints a fresh key per
    // install. Every buyer's JavaScript would have carried *our* key, Reverb
    // would have rejected every connection, and live tracking plus the
    // notification bell would have died silently on a site that looked fine.
    config([
        'broadcasting.connections.reverb.key' => 'buyers-own-key',
        'broadcasting.connections.reverb.options.host' => 'example.test',
        'broadcasting.connections.reverb.options.port' => 443,
        'broadcasting.connections.reverb.options.scheme' => 'https',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('reverb.key', 'buyers-own-key')
            ->where('reverb.host', 'example.test')
            ->where('reverb.port', 443)
            ->where('reverb.scheme', 'https'));
});

test('an empty reverb host means the site’s own host', function () {
    // The common deployment: Reverb behind the same reverse proxy as the site.
    // A buyer must not have to know that an empty value means "here".
    config([
        'broadcasting.connections.reverb.options.host' => '',
        'broadcasting.connections.reverb.options.scheme' => '',
    ]);

    $config = ReverbConfig::forBrowser(Request::create('https://urbanserve.test/services'));

    expect($config['host'])->toBe('urbanserve.test')
        ->and($config['scheme'])->toBe('https');
});

test('the reverb secret never reaches the browser', function () {
    config([
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.secret' => 'the-signing-secret',
        'broadcasting.connections.reverb.app_id' => '424242',
    ]);

    $content = (string) $this->get(route('home'))->getContent();

    // The key is the browser's to have; the secret signs channel auth and the
    // app id identifies the app to the server. Neither is the browser's business.
    expect($content)->toContain('public-key')
        ->and($content)->not->toContain('the-signing-secret')
        ->and($content)->not->toContain('424242');
});

test('the deployment guide is copy-paste, with this install’s own paths', function () {
    $guide = app(DeploymentGuide::class)->handle();

    expect($guide['cron'])->toContain(base_path())
        ->and($guide['cron'])->toContain('schedule:run')
        ->and($guide['supervisor'])->toContain('queue:work')
        ->and($guide['supervisor'])->toContain('reverb:start')
        ->and($guide['systemd_queue'])->toContain('queue:work')
        ->and($guide['systemd_reverb'])->toContain('reverb:start');
});

test('the installer’s last screen hands over the three processes', function () {
    // All three fail silently. The wizard finishing green while the site is half
    // dead is the failure this screen exists to prevent.
    $this->get(route('install.finish'))
        ->assertInertia(fn ($page) => $page
            ->component('installer/finish')
            ->has('deployment.cron')
            ->has('deployment.supervisor')
            ->has('deployment.systemd_queue')
            ->has('deployment.systemd_reverb'));
})->skip(fn () => InstallLock::installed(), 'The installer is locked once installed.');

test('the scheduler heartbeat asks a worker to vouch for itself', function () {
    Queue::fake();

    $this->artisan('system:heartbeat')->assertSuccessful();

    // The scheduler cannot vouch for the queue: only a worker picking the job up
    // proves a worker exists.
    Queue::assertPushed(QueueHeartbeat::class);
});

test('the queue heartbeat stamps its own clock', function () {
    app(QueueHeartbeat::class)->handle(app(SettingsRegistry::class));

    expect(app(ScheduleStatus::class)->queueLastRun())->not->toBeNull();
});

test('a dead queue is not blamed on a dead cron', function () {
    $status = app(ScheduleStatus::class);
    $settings = app(SettingsRegistry::class);

    // No cron at all: there is nothing for a worker to pick up, so blaming the
    // worker would send the operator after the wrong process.
    $settings->set(ScheduleStatus::LAST_RUN_KEY, '');
    expect($status->isStale())->toBeTrue()
        ->and($status->queueIsStale())->toBeFalse();

    // Cron alive, worker silent: now it is the worker.
    $settings->set(ScheduleStatus::LAST_RUN_KEY, now()->toIso8601String());
    $settings->set(ScheduleStatus::QUEUE_LAST_RUN_KEY, now()->subDay()->toIso8601String());
    expect($status->queueIsStale())->toBeTrue();

    $settings->set(ScheduleStatus::QUEUE_LAST_RUN_KEY, now()->toIso8601String());
    expect($status->queueIsStale())->toBeFalse();
});

test('the system screen shows the worker and the processes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.system.index'))
        ->assertInertia(fn ($page) => $page
            ->has('queue.is_stale')
            ->has('deployment.supervisor'));
});

test('a base install has the rows the site cannot work without', function () {
    // The seeder list in InstallerController is a promise, and this is the
    // promise. `lang/en.json` hangs off the `en` language row (M14); the footer
    // hangs off the legal pages; the home page IS a page (M20), and a buyer who
    // never sees a `home` row never discovers the home page is editable.
    expect(Language::query()->where('code', 'en')->exists())->toBeTrue()
        ->and(Page::query()->where('slug', Page::HOME_SLUG)->exists())->toBeTrue()
        ->and(Page::query()->where('slug', 'terms-and-conditions')->exists())->toBeTrue()
        ->and(Page::query()->where('slug', 'privacy-policy')->exists())->toBeTrue();
});
