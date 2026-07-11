<?php

use App\Domain\Admin\Actions\StartImpersonation;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function impersonationAdmin(): User
{
    return User::factory()->admin()->create();
}

it('lets an admin impersonate a customer and audits the start', function () {
    $admin = impersonationAdmin();
    $customer = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.impersonate.store', $customer))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($customer);

    expect(session(StartImpersonation::SESSION_KEY))->toBe($admin->id);

    $log = ActivityLog::query()->where('action', 'impersonation.start')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($customer->id);
});

it('sends an impersonated provider to the provider panel', function () {
    $provider = User::factory()->provider()->create();

    $this->actingAs(impersonationAdmin())
        ->post(route('admin.impersonate.store', $provider))
        ->assertRedirect(route('provider.dashboard'));

    $this->assertAuthenticatedAs($provider);
});

it('never impersonates another admin', function () {
    $admin = impersonationAdmin();
    $other = impersonationAdmin();

    $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.impersonate.store', $other))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasErrors('impersonate');

    $this->assertAuthenticatedAs($admin);

    expect(session()->has(StartImpersonation::SESSION_KEY))->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'impersonation.start')->count())->toBe(0);
});

it('refuses to nest impersonation', function () {
    $admin = impersonationAdmin();
    $customer = User::factory()->create();

    $request = request();
    $request->setLaravelSession(app('session.store'));
    $request->session()->put(StartImpersonation::SESSION_KEY, $admin->id);

    app(StartImpersonation::class)->handle($request, $admin, $customer);
})->throws(ValidationException::class);

it('blocks non-admins from starting impersonation', function () {
    $customer = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.impersonate.store', $target))
        ->assertForbidden();
});

it('shares the banner prop while impersonating and hides it otherwise', function () {
    $admin = impersonationAdmin();
    $customer = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.impersonate.store', $customer));

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('impersonation.user_name', $customer->name));

    $this->post(route('logout'));

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('impersonation', null));
});

it('returns to the admin session on leave and audits the stop', function () {
    $admin = impersonationAdmin();
    $customer = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.impersonate.store', $customer));

    $this->delete(route('impersonate.destroy'))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);

    expect(session()->has(StartImpersonation::SESSION_KEY))->toBeFalse();

    $log = ActivityLog::query()->where('action', 'impersonation.stop')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($customer->id);
});

it('403s a leave request when nobody is impersonating', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('impersonate.destroy'))
        ->assertForbidden();
});

it('kills the whole session when the stashed admin no longer exists', function () {
    $admin = impersonationAdmin();
    $customer = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.impersonate.store', $customer));

    $admin->forceDelete();

    $this->delete(route('impersonate.destroy'))->assertRedirect(route('login'));

    $this->assertGuest();
});
