<?php

namespace Database\Factories;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduled = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

        return [
            'code' => 'BK-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id' => User::factory(),
            'provider_id' => null,
            'address_id' => null,
            // NYC coords on purpose: the test suite seeds Bengaluru demo zones
            // before every test, so factory data must stay outside them.
            'address_snapshot' => [
                'label' => 'home',
                'line1' => '1 Test Street',
                'line2' => null,
                'city' => 'Testville',
                'postal_code' => '000001',
                'lat' => 40.7128,
                'lng' => -74.006,
            ],
            'contact_phone' => '9'.fake()->numerify('#########'),
            'contact_phone_alt' => null,
            'zone_id' => null,
            'scheduled_at' => $scheduled,
            'slot_end_at' => $scheduled->addHour(),
            'status' => BookingStatus::Placed,
            'subtotal' => '500.00',
            'addon_total' => '0.00',
            'discount' => '0.00',
            'tax' => '90.00',
            'tax_breakup' => ['label' => 'GST', 'percent' => 18.0, 'cgst' => 45.0, 'sgst' => 45.0, 'igst' => 0.0],
            'total' => '590.00',
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => PaymentMethod::Cash,
            'job_otp_code' => '1234',
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function withProvider(?User $provider = null): static
    {
        return $this->state(fn (): array => [
            'provider_id' => $provider === null ? User::factory() : $provider->id,
        ]);
    }

    public function scheduledAt(CarbonImmutable $slot): static
    {
        return $this->state(fn (): array => [
            'scheduled_at' => $slot,
            'slot_end_at' => $slot->addHour(),
        ]);
    }
}
