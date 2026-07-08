<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\CartManager;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Bookings\PriceQuote;
use App\Domain\Bookings\SlotGenerator;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Address;
use App\Models\Booking;
use App\Models\ServiceAddon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceBooking
{
    public function __construct(
        private readonly CartManager $cart,
        private readonly SlotGenerator $slots,
        private readonly BookingStateMachine $machine,
        private readonly SettingsRegistry $settings,
        private readonly PriceQuote $quote,
    ) {}

    /**
     * Turn the session cart into a placed booking: zone-gate every service
     * against the chosen address, validate the slot, snapshot names/prices/
     * address/tax, generate the human code + job OTP, and open the status
     * history. Cash (pay after service) keeps the booking unpaid until
     * completion; gateway payments arrive with M08.
     *
     * @param  list<UploadedFile>  $photos
     */
    public function handle(
        User $customer,
        Address $address,
        CarbonImmutable $scheduledAt,
        PaymentMethod $paymentMethod,
        ?string $notes,
        array $photos = [],
    ): Booking {
        $lines = $this->cart->detailed();

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
        }

        $blocked = $this->cart->blockedServiceNames($lines, $address->zone_id);

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'address_id' => __('Not available at this address: :services. Pick another address or remove them from your cart.', [
                    'services' => implode(', ', $blocked),
                ]),
            ]);
        }

        if (! $this->slots->isBookable($scheduledAt)) {
            throw ValidationException::withMessages([
                'scheduled_at' => __('That time slot is no longer available. Please pick another.'),
            ]);
        }

        $quote = $this->quote->fromLines($lines);

        $booking = DB::transaction(function () use ($customer, $address, $scheduledAt, $paymentMethod, $notes, $lines, $quote) {
            $booking = Booking::query()->create([
                'code' => Str::uuid()->toString(), // placeholder until the id exists
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'address_snapshot' => [
                    'label' => $address->label->value,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'city' => $address->city,
                    'postal_code' => $address->postal_code,
                    'lat' => (float) $address->lat,
                    'lng' => (float) $address->lng,
                ],
                'zone_id' => $address->zone_id,
                'scheduled_at' => $scheduledAt,
                'slot_end_at' => $scheduledAt->addMinutes($this->slots->slotMinutes()),
                'status' => BookingStatus::Placed,
                'subtotal' => number_format($quote['subtotal'], 2, '.', ''),
                'addon_total' => number_format($quote['addon_total'], 2, '.', ''),
                'discount' => number_format($quote['discount'], 2, '.', ''),
                'tax' => number_format($quote['tax'], 2, '.', ''),
                'tax_breakup' => [
                    'label' => $quote['tax_label'],
                    'percent' => $quote['tax_percent'],
                    'cgst' => $quote['cgst'],
                    'sgst' => $quote['sgst'],
                    'igst' => 0.0,
                ],
                'total' => number_format($quote['total'], 2, '.', ''),
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => $paymentMethod,
                'job_otp_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'notes' => $notes,
            ]);

            $booking->update(['code' => $this->makeCode($booking->id)]);

            foreach ($lines as $line) {
                $booking->items()->create([
                    'service_id' => $line['service']->id,
                    'name_snapshot' => $line['service']->name,
                    'price_snapshot' => $line['service']->price,
                    'qty' => $line['qty'],
                    'addons_snapshot' => $line['addons']->map(fn (ServiceAddon $addon): array => [
                        'id' => $addon->id,
                        'name' => $addon->name,
                        'price' => (string) $addon->price,
                    ])->values()->all(),
                ]);
            }

            $this->machine->initialize($booking, BookingActor::Customer, $customer);

            return $booking;
        });

        foreach ($photos as $photo) {
            $booking->addMedia($photo)->toMediaCollection('problem_photos');
        }

        $this->cart->clear();

        BookingPlaced::dispatch($booking);

        return $booking;
    }

    private function makeCode(int $id): string
    {
        return sprintf(
            '%s-%s-%06d',
            $this->settings->string('booking.code_prefix', 'BK') ?: 'BK',
            now()->format('Y'),
            $id,
        );
    }
}
