<?php

use App\Domain\Bookings\SlotGenerator;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// NYC/London coordinates keep clear of the seeded Bengaluru demo zones.

function checkoutSlot(): string
{
    $days = app(SlotGenerator::class)->days();

    return $days[0]['slots'][0]['value'];
}

function checkoutCustomer(): array
{
    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->at(40.7128, -74.0060)->default()->create();

    return [$customer, $address];
}

test('guests are sent to login before checkout', function () {
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
});

test('an empty cart bounces back to the cart page', function () {
    [$customer] = checkoutCustomer();

    $this->actingAs($customer)
        ->get(route('checkout.show'))
        ->assertRedirect(route('cart.show'));
});

test('placing a booking snapshots everything and opens the history', function () {
    [$customer, $address] = checkoutCustomer();
    $service = Service::factory()->create(['price' => 500]);
    $addon = ServiceAddon::factory()->for($service)->create(['price' => 100]);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1, 'addon_ids' => [$addon->id]]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => checkoutSlot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
        'notes' => 'Ring the bell twice.',
    ])->assertRedirect();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();

    expect($booking->code)->toMatch('/^BK-\d{4}-\d{6}$/')
        ->and($booking->status->value)->toBe('placed')
        ->and($booking->job_otp_code)->toMatch('/^\d{4}$/')
        ->and((string) $booking->subtotal)->toBe('500.00')
        ->and((string) $booking->addon_total)->toBe('100.00')
        ->and((string) $booking->tax)->toBe('108.00') // 18% GST default
        ->and((string) $booking->total)->toBe('708.00')
        ->and((float) $booking->tax_breakup['cgst'])->toBe(54.0) // json round-trip may drop the .0
        ->and($booking->address_snapshot['line1'])->toBe($address->line1)
        ->and($booking->contact_phone)->toBe('9876500001')
        ->and($booking->notes)->toBe('Ring the bell twice.');

    $item = $booking->items()->sole();
    expect($item->name_snapshot)->toBe($service->name)
        ->and((string) $item->price_snapshot)->toBe('500.00')
        ->and($item->addons_snapshot[0]['name'])->toBe($addon->name);

    $history = $booking->statusHistory()->sole();
    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe('placed')
        ->and($history->actor_type)->toBe('customer');

    // Cart is spent.
    expect($this->app['session.store']->get('cart.items', []))->toBe([]);
});

test('a zone-restricted service blocks an out-of-zone address at checkout', function () {
    [$customer, $address] = checkoutCustomer(); // NYC address, no zone
    $london = Zone::factory()->around(51.5074, -0.1278)->create();
    $service = Service::factory()->create();
    $service->zones()->attach($london);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => checkoutSlot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
    ])->assertSessionHasErrors('address_id');

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('an in-zone address passes the gate', function () {
    $zone = Zone::factory()->around(40.7128, -74.0060)->create();
    $customer = User::factory()->customer()->create();
    $address = Address::factory()->for($customer)->at(40.7128, -74.0060)->default()->create(['zone_id' => $zone->id]);

    $service = Service::factory()->create();
    $service->zones()->attach($zone);

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => checkoutSlot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
    ])->assertSessionHasNoErrors();

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeTrue();
});

test('off-grid slots are rejected', function () {
    [$customer, $address] = checkoutCustomer();
    $service = Service::factory()->create();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $offGrid = CarbonImmutable::parse(checkoutSlot())->addMinutes(17)->toIso8601String();

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => $offGrid,
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
    ])->assertSessionHasErrors('scheduled_at');
});

test('someone else\'s address is rejected', function () {
    [$customer] = checkoutCustomer();
    $stranger = Address::factory()->create();
    $service = Service::factory()->create();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.store'), [
        'address_id' => $stranger->id,
        'scheduled_at' => checkoutSlot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
    ])->assertSessionHasErrors('address_id');
});

test('problem photos land on the private disk and are policy-guarded', function () {
    Storage::fake('local');
    [$customer, $address] = checkoutCustomer();
    $service = Service::factory()->create();

    $this->actingAs($customer);
    $this->post(route('cart.add'), ['service_id' => $service->id, 'qty' => 1]);

    $this->post(route('checkout.store'), [
        'address_id' => $address->id,
        'scheduled_at' => checkoutSlot(),
        'payment_method' => 'cash',
        'contact_phone' => '9876500001',
        'photos' => [
            UploadedFile::fake()->image('before-1.jpg'),
            UploadedFile::fake()->image('before-2.jpg'),
        ],
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $media = $booking->getMedia('problem_photos');

    expect($media)->toHaveCount(2);

    $this->get(route('bookings.photos.show', [$booking, $media[0]]))->assertOk();

    $stranger = User::factory()->customer()->create();
    $this->actingAs($stranger)
        ->get(route('bookings.photos.show', [$booking, $media[0]]))
        ->assertForbidden();
});
