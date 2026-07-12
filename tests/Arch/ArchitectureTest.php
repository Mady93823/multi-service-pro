<?php

use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\SettingsGroupRegistry;

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
