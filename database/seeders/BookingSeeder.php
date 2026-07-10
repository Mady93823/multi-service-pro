<?php

namespace Database\Seeders;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Dispatch\Enums\DispatchMode;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo bookings for the demo customer, so every screen is demo-able right
 * after `migrate:fresh --seed`: one completed cash job with the full status
 * history, two completed wallet-paid jobs that give the provider a payable
 * balance (M09), one upcoming, one searching with a live offer and one the
 * provider has accepted (M06). Idempotent: skips when the customer has
 * bookings.
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

        // M09 demo: the cash job above leaves the provider *owing* commission,
        // so two wallet-paid jobs give them a real positive balance and the
        // payout request → admin approve → mark-paid loop is clickable.
        foreach ([12, 9] as $daysAgo) {
            $this->completedOnlineJob($customer, $provider, $address, $services[0], $daysAgo);
        }

        // Upcoming booking three days out, freshly placed.
        $upcoming = $this->makeBooking(
            $customer,
            $address,
            $services->get(1) ?? $services[0],
            CarbonImmutable::now()->addDays(3)->setTime(11, 0),
        );
        $machine->initialize($upcoming, BookingActor::Customer, $customer);

        // M06 dispatch demo: a job searching for a pro with a live offer to the
        // demo provider (shows on the provider's Jobs screen), and one they have
        // already accepted (shows with action buttons).
        $searching = $this->makeBooking(
            $customer,
            $address,
            $services[0],
            CarbonImmutable::now()->addDay()->setTime(9, 0),
        );
        $machine->initialize($searching, BookingActor::Customer, $customer);
        $machine->transition($searching, BookingStatus::Searching, BookingActor::System, null, __('Seeded demo data.'));
        $searching->dispatchOffers()->create([
            'provider_id' => $provider->id,
            'strategy' => DispatchMode::Nearest->value,
            'status' => OfferStatus::Offered->value,
            'round' => 1,
            'distance_km' => '2.40',
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $accepted = $this->makeBooking(
            $customer,
            $address,
            $services->get(1) ?? $services[0],
            CarbonImmutable::now()->addDay()->setTime(15, 0),
        );
        $machine->initialize($accepted, BookingActor::Customer, $customer);
        $accepted->provider_id = $provider->id;

        foreach ([BookingStatus::Searching, BookingStatus::Assigned, BookingStatus::Accepted] as $status) {
            $machine->transition($accepted, $status, BookingActor::System, null, __('Seeded demo data.'));
        }
    }

    /**
     * A wallet-paid job the provider finished a while back. The money settled
     * before completion, so no cash is owed and the earning is theirs.
     */
    private function completedOnlineJob(User $customer, User $provider, Address $address, Service $service, int $daysAgo): void
    {
        $booking = $this->makeBooking(
            $customer,
            $address,
            $service,
            CarbonImmutable::now()->subDays($daysAgo)->setTime(10, 0),
            PaymentMethod::Wallet,
        );

        $booking->payments()->create([
            'gateway' => PaymentProvider::Wallet,
            'amount' => $booking->total,
            'currency' => app(SettingsRegistry::class)->string('localization.currency', 'INR') ?: 'INR',
            'status' => PaymentState::Captured,
            'captured_at' => now(),
        ]);
        $booking->update(['payment_status' => PaymentStatus::Paid]);

        $machine = app(BookingStateMachine::class);
        $machine->initialize($booking, BookingActor::Customer, $customer);
        $booking->provider_id = $provider->id;

        foreach ([
            BookingStatus::Searching,
            BookingStatus::Assigned,
            BookingStatus::Accepted,
            BookingStatus::EnRoute,
            BookingStatus::Arrived,
            BookingStatus::InProgress,
            BookingStatus::Completed,
        ] as $status) {
            $machine->transition($booking, $status, BookingActor::System, null, __('Seeded demo data.'));
        }

        // Stand in for `earnings:release`: the hold window would have elapsed
        // by now on a job this old, and the seeder cannot wait for the clock.
        $booking->earnings()->update([
            'status' => EarningStatus::Available->value,
            'available_at' => now()->subDays($daysAgo - 1),
        ]);
    }

    private function makeBooking(
        User $customer,
        Address $address,
        Service $service,
        CarbonImmutable $scheduledAt,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
    ): Booking {
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
            'payment_method' => $paymentMethod,
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
