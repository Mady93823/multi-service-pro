<?php

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockRegistry;
use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\SettingsGroupRegistry;
use Illuminate\Support\Facades\File;

/**
 * Architecture rules (Pest arch). They hold for every module, forever, and they
 * catch the class of mistake no feature test can see: a layering breach, a
 * forgotten debug call, an Action that quietly grew a second entry point.
 *
 * A rule enforced here is cheaper than a convention nobody re-reads.
 */
arch('no debugging leftovers ship')
    ->expect(['dd', 'ddd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('the domain layer never depends on HTTP')
    // Business logic must be callable from a controller, a console command, a
    // queued job or a test with no request in sight (Actions pattern, §07).
    // This is why SettingsGroup::rules() takes an array, not a Request.
    ->expect('App\Domain')
    ->not->toUse(['App\Http', 'Inertia\Inertia', 'Illuminate\Http\Request'])
    // Impersonation (M13) is the one honest exception: swapping the logged-in
    // user *is* a session operation, so its actions take the Request.
    ->ignoring('App\Domain\Admin\Actions');

arch('models stay Eloquent-only')
    // Models are relationships, scopes and casts (§07). A model reaching for
    // the authenticated user or a domain action is business logic in disguise.
    ->expect('App\Models')
    ->not->toUse([
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\Gate',
        'App\Domain\Bookings\Actions',
        'App\Domain\Payments\Actions',
        'App\Http',
    ]);

arch('controllers never write to the database directly')
    // A controller reaching for a transaction or the schema is doing work an
    // Action should own. The installer is exempt: it inspects the database
    // before the application it is installing exists (M15).
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema'])
    ->ignoring('App\Http\Controllers\Installer');

arch('validation lives in form requests')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('policies are suffixed and live in one place')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('every domain enum is string-backed')
    // A string-backed enum survives a JSON round-trip to React and back; an int
    // one turns statuses into magic numbers in the wire format.
    ->expect('App\Domain')
    ->enums()
    ->toBeStringBackedEnums();

arch('env() is read through config only')
    // A cached config (`php artisan config:cache`, which the installer runs)
    // freezes env() to null at runtime — reading it outside config/ is a bug
    // that only shows up on the buyer's server (D8).
    ->expect('env')
    ->not->toBeUsed();

test('every Action exposes handle(), or a documented set of sibling verbs', function () {
    // Reflection rather than an arch ignore-list: the rule is about Actions, and
    // only Actions — services, read models and value objects are free.
    //
    // Three actions predate this rule and earn their exception: each is one
    // guarded core reached through sibling verbs that must not be confusable at
    // the call site (an admin cancel is not a customer cancel — the refund and
    // the fee differ). Anything else grows a `handle()` or becomes two actions.
    $multiVerb = [
        'App\Domain\Bookings\Actions\CancelBooking' => ['byCustomer', 'byAdmin'],
        'App\Domain\Earnings\Actions\ProcessPayout' => ['approve', 'markPaid', 'reject'],
        'App\Domain\Reviews\Actions\ModerateReview' => ['hide', 'unhide'],
    ];

    $actions = [];

    foreach (glob(app_path('Domain/*/Actions/*.php')) ?: [] as $file) {
        $module = basename(dirname($file, 2));
        $class = 'App\\Domain\\'.$module.'\\Actions\\'.basename($file, '.php');

        $this->assertTrue(class_exists($class), "{$class} does not autoload — check the namespace.");

        $reflection = new ReflectionClass($class);
        $names = array_values(array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $method): bool => ! $method->isConstructor()
                    && $method->getDeclaringClass()->getName() === $class,
            ),
        ));

        sort($names);

        if (array_key_exists($class, $multiVerb)) {
            $expected = $multiVerb[$class];
            sort($expected);

            $this->assertSame($expected, $names, "{$class}'s verbs changed — update the allowlist deliberately.");
        } else {
            $this->assertContains('handle', $names, "{$class} must expose handle().");
        }

        $actions[] = $class;
    }

    // Sanity: the glob found the Actions rather than silently matching nothing.
    expect(count($actions))->toBeGreaterThan(20);

    // And the allowlist must not rot — a renamed/deleted action has to be noticed.
    foreach (array_keys($multiVerb) as $class) {
        $this->assertContains($class, $actions, "{$class} is allowlisted but no longer exists.");
    }
});

test('every settings group is registered and self-consistent', function () {
    // The class exists → it is reachable. A group that is written but never
    // added to the registry is a settings screen nobody can open (D24).
    $registered = array_map(
        fn (SettingsGroup $group): string => $group::class,
        array_values(app(SettingsGroupRegistry::class)->all()),
    );

    foreach (glob(app_path('Domain/Settings/Groups/*.php')) ?: [] as $file) {
        $class = 'App\\Domain\\Settings\\Groups\\'.basename($file, '.php');

        if ($class === SettingsGroup::class) {
            continue;
        }

        $this->assertContains($class, $registered, "{$class} is not in SettingsGroupRegistry — its screen is unreachable.");
    }
});

test('listeners are auto-discovered exactly once', function () {
    // M11's most expensive bug: an explicit Event::listen() for a listener that
    // Laravel already auto-discovers fires it twice (every placed booking was
    // dispatched twice for a whole milestone). Register in one place: nowhere.
    foreach (glob(app_path('Providers/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        $this->assertStringNotContainsString(
            'Event::listen',
            $source,
            basename($file).' registers a listener explicitly; app/Listeners is auto-discovered and it will fire twice.',
        );
    }
});

test('the installer environment is bootstrapped before anything can resolve a cache store', function () {
    // The bug that cost a real install (P7.7). `bootstrapInstallerEnvironment()`
    // sat in boot(), two lines *after* `defineRateLimiters()` — and touching the
    // RateLimiter facade resolves a singleton whose factory reads `cache.default`
    // once and memoizes the store forever. So the limiter captured the *database*
    // cache before the override could say "file", and every throttled /install
    // route queried a `cache` table the wizard had not created yet. The wizard
    // 500'd until the buyer ran `php artisan migrate` by hand, which is the exact
    // job a web installer exists to do for them.
    //
    // A config override that has to beat every other service to the container
    // belongs in register(). This is a source check because the thing being
    // asserted is *when* the call happens, and `runningUnitTests()` means the
    // method itself never does anything under test.
    $source = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

    preg_match('/public function register\(\): void\s*\{(.*?)\n    \}/s', $source, $register);
    preg_match('/public function boot\(\): void\s*\{(.*?)\n    \}/s', $source, $boot);

    // assertString*, not expect()->toContain(): Pest's toContain() is variadic,
    // so a "message" passed to it becomes a second needle and the test asserts
    // its own explanation is in the source. (It did. That is how this was found.)
    $this->assertStringContainsString(
        'bootstrapInstallerEnvironment',
        $register[1] ?? '',
        'The installer driver overrides must run in register(), before any singleton captures a cache store.',
    );

    $this->assertStringNotContainsString(
        'bootstrapInstallerEnvironment',
        $boot[1] ?? '',
        'boot() is too late: RateLimiter::for() resolves the limiter against whatever cache.default says at that moment.',
    );
});

test('every block type in the registry has a React renderer', function () {
    // The two halves of the block registry (M20) must agree: a PHP block type
    // with no renderer is a block an admin can add and a visitor never sees.
    $index = (string) file_get_contents(resource_path('js/components/blocks/index.tsx'));

    foreach (app(BlockRegistry::class)->types() as $type) {
        $file = resource_path('js/components/blocks/'.str_replace('_', '-', $type).'.tsx');

        $this->assertFileExists($file, "Block [{$type}] has no renderer.");
        $this->assertStringContainsString("{$type}:", $index, "Block [{$type}] is not mapped in the renderer registry.");
    }
});

test('every block class is registered', function () {
    $registered = array_map(fn (Block $block): string => $block::class, array_values(app(BlockRegistry::class)->all()));

    foreach (glob(app_path('Domain/Blocks/Types/*.php')) ?: [] as $file) {
        $class = 'App\\Domain\\Blocks\\Types\\'.basename($file, '.php');

        $this->assertContains($class, $registered, "{$class} is not in BlockRegistry — an admin can never add it.");
    }
});

test('upload allowlists come from UploadRules, never from a hand-typed mime list', function () {
    // Ten form requests each carried their own copy of `mimes:jpg,jpeg,png,webp`
    // (P7.1). They agreed — which is why nobody would have noticed the day one of
    // them drifted, and the one that drifts is the one that admits the file the
    // rest refuse. There is one allowlist now, and this is what keeps it one.
    $offenders = [];

    foreach (File::allFiles(app_path('Http/Requests')) as $file) {
        $source = (string) file_get_contents($file->getPathname());

        if (preg_match('/\bmimes:|\bmimetypes:/', $source) === 1) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([], 'These form requests spell out a mime allowlist instead of calling UploadRules.');
});

test('no model unguards mass assignment', function () {
    // `$guarded = []` turns every column into a form field, and the columns that
    // hurt are the ones added later: is_active, approval_status, commission_percent.
    foreach (File::allFiles(app_path('Models')) as $file) {
        $source = (string) file_get_contents($file->getPathname());

        $this->assertStringNotContainsString(
            '$guarded',
            $source,
            $file->getFilename().' unguards mass assignment — models list $fillable.',
        );
    }
});
