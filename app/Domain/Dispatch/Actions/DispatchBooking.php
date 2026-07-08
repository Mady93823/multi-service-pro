<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Bookings\BookingStateMachine;
use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Dispatch\EligibleProviders;
use App\Domain\Dispatch\Enums\DispatchMode;
use App\Domain\Dispatch\Enums\OfferStatus;
use App\Domain\Dispatch\Events\BookingOffered;
use App\Domain\Dispatch\Events\DispatchExhausted;
use App\Domain\Dispatch\Jobs\ExpireDispatchRound;
use App\Domain\Dispatch\StrategyFactory;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\DispatchOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs one dispatch round for a booking (M06): move it into `searching`, ask
 * the configured strategy which eligible providers to offer, write the offer
 * rows and schedule the timeout that re-offers the next batch. Idempotent per
 * round — declines and the timeout job both funnel back here, and the offer
 * finder already excludes anyone previously offered.
 */
class DispatchBooking
{
    public function __construct(
        private readonly BookingStateMachine $machine,
        private readonly EligibleProviders $eligible,
        private readonly StrategyFactory $factory,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(Booking $booking): void
    {
        if (! in_array($booking->status, [BookingStatus::Placed, BookingStatus::Searching], true)) {
            return;
        }

        $round = (int) DispatchOffer::query()->where('booking_id', $booking->id)->max('round') + 1;

        if ($round > $this->settings->integer('dispatch.max_rounds', 5)) {
            $this->exhaust($booking);

            return;
        }

        $strategy = $this->factory->make(
            DispatchMode::tryFrom($this->settings->string('dispatch.mode', 'nearest')) ?? DispatchMode::Nearest,
        );

        $picked = $strategy->pick($this->eligible->forBooking($booking));

        if ($picked->isEmpty()) {
            // Nobody to offer. A freshly placed booking simply waits for a
            // manual push; a booking already searching has run out of
            // candidates, so alert the admin (it stays visible in searching).
            $this->exhaust($booking);

            return;
        }

        if ($booking->status === BookingStatus::Placed) {
            $this->machine->transition($booking, BookingStatus::Searching, BookingActor::System);
        }

        $timeoutSeconds = $this->settings->integer('dispatch.offer_timeout_seconds', 60);
        $expiresAt = now()->addSeconds($timeoutSeconds);

        /** @var Collection<int, DispatchOffer> $offers */
        $offers = DB::transaction(fn (): Collection => $picked->map(fn ($candidate): DispatchOffer => DispatchOffer::query()->create([
            'booking_id' => $booking->id,
            'provider_id' => $candidate->providerId(),
            'strategy' => $strategy->mode()->value,
            'status' => OfferStatus::Offered->value,
            'round' => $round,
            'distance_km' => number_format($candidate->distanceKm, 2, '.', ''),
            'offered_at' => now(),
            'expires_at' => $expiresAt,
        ]))->values());

        ExpireDispatchRound::dispatch($booking->id, $round)->delay($expiresAt);

        BookingOffered::dispatch($booking, $offers);
    }

    private function exhaust(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Searching) {
            DispatchExhausted::dispatch($booking);
        }
    }
}
