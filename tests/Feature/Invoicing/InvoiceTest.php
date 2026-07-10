<?php

use App\Domain\Invoicing\BookingInvoice;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use App\Support\Money;
use Tests\Support\EarningsFixtures;

beforeEach(function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('invoice.prefix', 'INV');
    $settings->set('invoice.gstin', '29AAAAA0000A1Z5');
    $settings->set('invoice.company_name', 'UrbanServe Pvt Ltd');
});

/** @return array{0: User, 1: Booking} */
function invoiceBooking(): array
{
    $customer = User::factory()->customer()->create();
    $booking = EarningsFixtures::booking();
    $booking->forceFill(['customer_id' => $customer->id])->save();

    return [$customer, EarningsFixtures::markPaid($booking->fresh())];
}

test('an unpaid booking has no invoice', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)->get(route('bookings.invoice', $booking->id))->assertNotFound();
});

test('the customer downloads a pdf invoice for a paid booking', function () {
    [$customer, $booking] = invoiceBooking();

    $response = $this->actingAs($customer)->get(route('bookings.invoice', $booking->id));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload(app(BookingInvoice::class)->filename($booking));

    // %PDF is the file magic — proves dompdf actually rendered something.
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('the invoice number is derived from the booking, so a reprint never renumbers', function () {
    [, $booking] = invoiceBooking();
    $invoice = app(BookingInvoice::class);

    expect($invoice->number($booking))
        ->toBe(sprintf('INV-%s-%06d', $booking->created_at->format('Y'), $booking->id))
        ->and($invoice->number($booking))->toBe($invoice->number($booking->fresh()));
});

test('another customer cannot fetch the invoice', function () {
    [, $booking] = invoiceBooking();

    $this->actingAs(User::factory()->customer()->create())
        ->get(route('bookings.invoice', $booking->id))
        ->assertForbidden();
});

test('the assigned provider cannot fetch the customer tax invoice', function () {
    [, $booking] = invoiceBooking();

    $this->actingAs($booking->provider)
        ->get(route('bookings.invoice', $booking->id))
        ->assertForbidden();
});

test('an admin can fetch any invoice', function () {
    [, $booking] = invoiceBooking();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('bookings.invoice', $booking->id))
        ->assertOk();
});

test('the invoice carries the seller GSTIN and the tax breakup from the booking snapshot', function () {
    [, $booking] = invoiceBooking();

    $data = app(BookingInvoice::class)->data($booking);

    expect($data['seller']['gstin'])->toBe('29AAAAA0000A1Z5')
        ->and($data['seller']['name'])->toBe('UrbanServe Pvt Ltd')
        ->and($data['tax'])->toBe(['label' => 'GST', 'percent' => 18.0, 'cgst' => 45.0, 'sgst' => 45.0, 'igst' => 0.0]);
});

test('a blank company name falls back to the branding app name', function () {
    app(SettingsRegistry::class)->set('invoice.company_name', null);
    app(SettingsRegistry::class)->set('branding.app_name', 'Acme Services');

    [, $booking] = invoiceBooking();

    expect(app(BookingInvoice::class)->data($booking)['seller']['name'])->toBe('Acme Services');
});

test('money is grouped the Indian way for rupees and the Western way otherwise', function () {
    expect(Money::format('100000', 'INR'))->toBe('₹1,00,000.00')
        ->and(Money::format('999.5', 'INR'))->toBe('₹999.50')
        ->and(Money::format('-190', 'INR'))->toBe('-₹190.00')
        ->and(Money::format('100000', 'USD'))->toBe('$100,000.00')
        ->and(Money::format('1234.5', 'AED'))->toBe('AED 1,234.50');
});
