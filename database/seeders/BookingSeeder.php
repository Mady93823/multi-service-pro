<?php

namespace Database\Seeders;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Two demo bookings for the demo customer: one completed with a full status
 * history and one upcoming — booking screens are demo-able right after
 * `migrate:fresh --seed`. Idempotent: skips when the customer has bookings.
 */
class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@demo.test')->first();
        $provider = User::query()->where('email', 'provider@demo.test')->first();

        if ($customer === null || $provider === null || $customer->bookings()->exists()) {
            return;
        }

        $address = $customer->addresses()->where('is_default', true)->first();
        $services = Service::query()->active()->orderBy('id')->take(2)->get();

        if ($address === null || $services->isEmpty()) {
            return;
        }

        $machine = app(BookingStateMachine::class);

        // Completed booking, five days back, with the full happy-path history.
        $completed = $this->makeBooking(
            $customer,
            $address,
            $services[0],
            CarbonImmutable::now()->subDays(5)->setTime(10, 0),
        );
        $machine->initialize($completed, BookingActor::Customer, $customer);

        $completed->provider_id = $provider->id;

        foreach ([
            BookingStatus::Searching,
            BookingStatus::Assigned,
            BookingStatus::Accepted,
            BookingStatus::EnRoute,
            BookingStatus::Arrived,
            BookingStatus::InProgress,
            BookingStatus::Completed,
        ] as $status) {
            $machine->transition($completed, $status, BookingActor::System, null, __('Seeded demo data.'));
        }

        $completed->update(['payment_status' => PaymentStatus::Paid]);

        // Upcoming booking three days out, freshly placed.
        $upcoming = $this->makeBooking(
            $customer,
            $address,
            $services->get(1) ?? $services[0],
            CarbonImmutable::now()->addDays(3)->setTime(11, 0),
        );
        $machine->initialize($upcoming, BookingActor::Customer, $customer);
    }

    private function makeBooking(User $customer, Address $address, Service $service, CarbonImmutable $scheduledAt): Booking
    {
        $settings = app(SettingsRegistry::class);

        $subtotal = (float) $service->price;
        $percent = $settings->decimal('payments.tax_percent', 18.0);
        $tax = round($subtotal * $percent / 100, 2);
        $cgst = round($tax / 2, 2);

        $booking = Booking::query()->create([
            'code' => 'SEED-'.uniqid(),
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
            'slot_end_at' => $scheduledAt->addMinutes($settings->integer('booking.slot_minutes', 60)),
            'status' => BookingStatus::Placed,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'addon_total' => '0.00',
            'discount' => '0.00',
            'tax' => number_format($tax, 2, '.', ''),
            'tax_breakup' => [
                'label' => $settings->string('payments.tax_label', 'GST'),
                'percent' => $percent,
                'cgst' => $cgst,
                'sgst' => round($tax - $cgst, 2),
                'igst' => 0.0,
            ],
            'total' => number_format($subtotal + $tax, 2, '.', ''),
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => PaymentMethod::Cash,
            'job_otp_code' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
        ]);

        $booking->update([
            'code' => sprintf(
                '%s-%s-%06d',
                $settings->string('booking.code_prefix', 'BK') ?: 'BK',
                now()->format('Y'),
                $booking->id,
            ),
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => $service->price,
            'qty' => 1,
            'addons_snapshot' => [],
        ]);

        return $booking;
    }
}
