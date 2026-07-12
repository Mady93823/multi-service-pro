<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Users\Actions\SetUserActive;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

test('guests and non-admins cannot reach the customers screen', function () {
    $customer = User::factory()->customer()->create();

    $this->get('/admin/customers')->assertRedirect('/login');

    $this->actingAs($customer)->get('/admin/customers')->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get('/admin/customers')->assertForbidden();
});

test('the customers list searches and filters', function () {
    $admin = User::factory()->admin()->create();
    $hit = User::factory()->customer()->create(['name' => 'Zebra Findme', 'email' => 'zebra@example.test']);
    User::factory()->customer()->create(['name' => 'Other Person']);

    $this->actingAs($admin)
        ->get('/admin/customers?search=Findme')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/customers/index')
            ->where('customers.meta.total', 1)
            ->where('customers.data.0.id', $hit->id));

    // A blocked customer only shows under the blocked filter.
    $hit->forceFill(['is_active' => false])->save();

    $this->actingAs($admin)
        ->get('/admin/customers?status=blocked')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customers.meta.total', 1));

    $this->actingAs($admin)
        ->get('/admin/customers?status=active&search=Findme')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customers.meta.total', 0));
});

test('the customer screen shows their history in one place', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    Booking::factory()->for($customer, 'customer')->create([
        'status' => BookingStatus::Completed,
        'total' => 1200,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/customers/show')
            ->where('customer.id', $customer->id)
            ->where('stats.bookings', 1)
            ->where('stats.completed', 1)
            ->where('stats.spent_total', 1200)
            ->has('bookings', 1)
            ->has('transactions')
            ->has('tickets'));
});

test('a provider cannot be opened as a customer', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.customers.show', User::factory()->provider()->create()))
        ->assertNotFound();
});

test('blocking a customer needs a reason, signs them out and is audited', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($admin)
        ->post(route('admin.customers.block', $customer), [])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)
        ->post(route('admin.customers.block', $customer), ['reason' => 'Chargeback fraud'])
        ->assertSessionHasNoErrors();

    $customer->refresh();

    expect($customer->is_active)->toBeFalse()
        ->and($customer->blocked_reason)->toBe('Chargeback fraud');

    expect(ActivityLog::query()
        ->where('action', 'user.blocked')
        ->where('subject_id', $customer->id)
        ->exists())->toBeTrue();

    // The block is enforced on the blocked user's very next request.
    $this->actingAs($customer)
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('unblocking clears the reason and lets them back in', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $customer->forceFill(['is_active' => false, 'blocked_reason' => 'Spam'])->save();

    $this->actingAs($admin)
        ->post(route('admin.customers.unblock', $customer))
        ->assertSessionHasNoErrors();

    $customer->refresh();

    expect($customer->is_active)->toBeTrue()
        ->and($customer->blocked_reason)->toBeNull();

    $this->actingAs($customer)->get('/dashboard')->assertOk();
});

test('an admin can never be blocked', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    // Not even through the action — the route is customer-scoped, the action is
    // the backstop for every future caller.
    expect(fn () => app(SetUserActive::class)->handle($admin, $other, false, 'nope'))
        ->toThrow(ValidationException::class);

    expect($other->fresh()->is_active)->toBeTrue();
});

test('a blocked customer cannot be impersonated', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $customer->forceFill(['is_active' => false])->save();

    $this->actingAs($admin)
        ->post(route('admin.impersonate.store', $customer))
        ->assertSessionHasErrors('impersonate');
});
