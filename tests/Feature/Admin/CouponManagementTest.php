<?php

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\User;

function couponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'NEWCODE1',
        'type' => 'flat',
        'value' => '75.00',
        'max_discount' => null,
        'min_order' => null,
        'usage_limit' => null,
        'per_user_limit' => null,
        'first_order_only' => false,
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
    ], $overrides);
}

test('customers cannot reach coupon admin', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get(route('admin.coupons.index'))
        ->assertForbidden();
});

test('an admin can create a coupon and the code is stored uppercase', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.coupons.store'), couponPayload(['code' => 'summer24']))
        ->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::query()->where('code', 'SUMMER24')->sole();
    expect((string) $coupon->value)->toBe('75.00');
});

test('a duplicate code is rejected', function () {
    Coupon::factory()->create(['code' => 'TAKEN1']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.coupons.store'), couponPayload(['code' => 'taken1']))
        ->assertSessionHasErrors('code');
});

test('a percentage over 100 is rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.coupons.store'), couponPayload(['type' => 'percent', 'value' => '150']))
        ->assertSessionHasErrors('value');
});

test('an admin can update a coupon', function () {
    $coupon = Coupon::factory()->create(['code' => 'EDITME1']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.coupons.update', $coupon), couponPayload(['code' => 'EDITME1', 'value' => '99.00', 'is_active' => false]))
        ->assertRedirect(route('admin.coupons.index'));

    $coupon->refresh();
    expect((string) $coupon->value)->toBe('99.00')
        ->and($coupon->is_active)->toBeFalse();
});

test('an unused coupon can be deleted', function () {
    $coupon = Coupon::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.coupons.destroy', $coupon))
        ->assertRedirect(route('admin.coupons.index'));

    expect(Coupon::query()->whereKey($coupon->id)->exists())->toBeFalse();
});

test('a redeemed coupon cannot be deleted — deactivate instead', function () {
    $coupon = Coupon::factory()->create();
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $coupon->usages()->create([
        'user_id' => $customer->id,
        'booking_id' => $booking->id,
        'discount_applied' => '50.00',
        'created_at' => now(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.coupons.destroy', $coupon))
        ->assertSessionHasErrors('coupon');

    expect(Coupon::query()->whereKey($coupon->id)->exists())->toBeTrue();
});

test('the coupon index renders with usage counts', function () {
    $coupon = Coupon::factory()->create(['code' => 'LISTME1']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.coupons.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/coupons/index')
                ->has('coupons.data')
                ->has('coupons.meta')
                ->where('coupons.data', fn ($data) => collect($data)->contains(fn ($row) => $row['code'] === 'LISTME1'))
        );
});
