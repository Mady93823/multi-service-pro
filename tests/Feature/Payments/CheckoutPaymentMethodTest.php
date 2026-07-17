<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Payments\WalletService;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * @return array{0: User, 1: Address}
 */
function payingCustomer(): array
{
    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->at(40.7128, -74.0060)->default()->create();

    return [$customer, $address];
}

function addServiceToCart(User $customer, float $price = 500): void
{
    $service = Service::factory()->create(['price' => $price]);

    test()->actingAs($customer);
    test()->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);
}

function checkoutWith(Address $address, string $method): TestResponse
{
    return test()->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => app(SlotGenerator::class)->days()[0]['slots'][0]['value'],
        'payment_method' => $method,
        'contact_phone' => '9876500003',
    ]);
}

test('checkout offers only the methods the platform has switched on', function () {
    [$customer] = payingCustomer();
    addServiceToCart($customer);

    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_id', 'rzp_test_key');
    $settings->set('payments.razorpay_key_secret', 'rzp_secret');

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('checkout')
            ->where('payment_methods', ['cash', 'razorpay', 'wallet'])
            ->where('wallet_balance', '0.00'),
        );
});

test('checkout hides pay-after-service when it is switched off', function () {
    [$customer] = payingCustomer();
    addServiceToCart($customer);

    app(SettingsRegistry::class)->set('payments.pay_after_service', false);

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('payment_methods', ['wallet']));
});

test('an unconfigured gateway is never offered at checkout', function () {
    [$customer, $address] = payingCustomer();
    addServiceToCart($customer);

    checkoutWith($address, 'razorpay')->assertSessionHasErrors('payment_method');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

test('a cash booking is placed immediately and dispatch is kicked off', function () {
    Event::fake([BookingPlaced::class]);
    [$customer, $address] = payingCustomer();
    addServiceToCart($customer);

    checkoutWith($address, 'cash')->assertRedirect();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_method)->toBe(PaymentMethod::Cash)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid);

    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('a gateway booking waits at pending_payment on the pay page and is not dispatched', function () {
    Event::fake([BookingPlaced::class]);
    [$customer, $address] = payingCustomer();
    addServiceToCart($customer);

    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_id', 'rzp_test_key');
    $settings->set('payments.razorpay_key_secret', 'rzp_secret');

    checkoutWith($address, 'razorpay')->assertRedirect();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_method)->toBe(PaymentMethod::Gateway);

    $this->get(route('bookings.pay', $booking))->assertOk();

    // No provider hears about a booking nobody has paid for.
    Event::assertNotDispatched(BookingPlaced::class);
});

test('a wallet booking with enough balance settles during checkout', function () {
    [$customer, $address] = payingCustomer();
    app(WalletService::class)->credit($customer, '1000.00', 'topup');
    addServiceToCart($customer);

    checkoutWith($address, 'wallet')->assertRedirect();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and(app(WalletService::class)->balance($customer))->toBe(410.0); // 1000 − 590
});

test('a wallet booking with too little balance lands on the pay page', function () {
    [$customer, $address] = payingCustomer();
    app(WalletService::class)->credit($customer, '10.00', 'topup');
    addServiceToCart($customer);

    checkoutWith($address, 'wallet')->assertSessionHas('error');

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and(app(WalletService::class)->balance($customer))->toBe(10.0);
});

test('bank transfer is offered only when it is on AND an account exists to pay into', function () {
    // The seeder ships a demo bank account — start from none (landmine 17).
    BankAccount::query()->delete();

    [$customer] = payingCustomer();
    addServiceToCart($customer);

    $settings = app(SettingsRegistry::class);

    // Switched on, but nowhere to send the money — a dead-end option (M22).
    $settings->set('payments.offline_enabled', true);

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('payment_methods', ['cash', 'wallet']));

    BankAccount::factory()->create();

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('payment_methods', ['cash', 'wallet', 'offline']));

    // An inactive account is not somewhere a customer can be sent either.
    BankAccount::query()->update(['is_active' => false]);

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('payment_methods', ['cash', 'wallet']));
});

test('an offline booking waits at pending_payment on the pay page and is not dispatched', function () {
    Event::fake([BookingPlaced::class]);
    BankAccount::query()->delete();

    [$customer, $address] = payingCustomer();
    addServiceToCart($customer);

    app(SettingsRegistry::class)->set('payments.offline_enabled', true);
    BankAccount::factory()->create();

    checkoutWith($address, 'offline')->assertRedirect();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_method)->toBe(PaymentMethod::Offline)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid);

    // The pay page is where the bank details live.
    $this->get(route('bookings.pay', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('methods.offline.enabled', true)
            ->has('methods.offline.accounts', 1),
        );

    Event::assertNotDispatched(BookingPlaced::class);
});

test('the pay page redirects away once the booking is no longer awaiting payment', function () {
    [$customer] = payingCustomer();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::Placed,
    ]);

    $this->actingAs($customer)
        ->get(route('bookings.pay', $booking))
        ->assertRedirect(route('bookings.show', $booking));
});
