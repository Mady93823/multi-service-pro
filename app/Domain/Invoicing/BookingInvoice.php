<?php

namespace App\Domain\Invoicing;

use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;

/**
 * GST-format tax invoice for a booking (M09, D9).
 *
 * Every figure comes from the booking's own snapshot columns — `tax_breakup`
 * was written at checkout, so changing the tax rate today can never reprint an
 * old invoice with new numbers.
 */
class BookingInvoice
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Derived from the booking id, so the same booking always reprints the
     * same invoice number without a column to keep in sync.
     */
    public function number(Booking $booking): string
    {
        return sprintf(
            '%s-%s-%06d',
            $this->settings->string('invoice.prefix', 'INV') ?: 'INV',
            ($booking->created_at ?? now())->format('Y'),
            $booking->id,
        );
    }

    /** An invoice exists once the customer has actually paid something. */
    public function isAvailableFor(Booking $booking): bool
    {
        return $booking->payment_status !== PaymentStatus::Unpaid;
    }

    public function filename(Booking $booking): string
    {
        return $this->number($booking).'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Booking $booking): array
    {
        $booking->loadMissing(['customer', 'items', 'payments']);

        /** @var array<string, mixed> $address */
        $address = $booking->address_snapshot;
        $breakup = $booking->tax_breakup ?? [];
        $currency = $this->settings->string('localization.currency', 'INR') ?: 'INR';

        return [
            'number' => $this->number($booking),
            'currency' => $currency,
            'issued_at' => ($booking->completed_at ?? $booking->created_at ?? now())
                ->timezone($this->settings->string('localization.timezone', 'Asia/Kolkata')),
            'booking' => $booking,
            'seller' => [
                // Blank company name falls back to the branding name so an
                // un-configured install still prints something sensible (D8).
                'name' => $this->settings->string('invoice.company_name')
                    ?: $this->settings->string('branding.app_name', (string) config('app.name')),
                'gstin' => $this->settings->string('invoice.gstin') ?: null,
                'address' => $this->settings->string('invoice.address') ?: null,
                'state' => $this->settings->string('invoice.state') ?: null,
            ],
            'buyer' => [
                'name' => $booking->customer?->name,
                'email' => $booking->customer?->email,
                'phone' => $booking->customer?->phone,
                'address' => $address,
            ],
            'place_of_supply' => $address['city'] ?? null,
            'items' => $booking->items->map(fn (BookingItem $item): array => [
                'name' => $item->name_snapshot,
                'qty' => $item->qty,
                'unit_price' => $item->price_snapshot,
                'addons' => $item->addons_snapshot ?? [],
                'line_total' => $item->lineTotal(),
            ])->all(),
            'tax' => [
                'label' => is_string($breakup['label'] ?? null) ? $breakup['label'] : $this->settings->string('payments.tax_label', 'GST'),
                'percent' => (float) ($breakup['percent'] ?? 0),
                'cgst' => (float) ($breakup['cgst'] ?? 0),
                'sgst' => (float) ($breakup['sgst'] ?? 0),
                'igst' => (float) ($breakup['igst'] ?? 0),
            ],
            'payments' => $booking->payments->map(fn (Payment $payment): array => [
                'gateway' => $payment->gateway->value,
                'status' => $payment->status->value,
                'reference' => $payment->gateway_ref,
                'captured_at' => $payment->captured_at,
            ])->all(),
        ];
    }
}
