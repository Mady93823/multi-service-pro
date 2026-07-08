<?php

namespace App\Domain\Bookings;

use App\Domain\Bookings\Enums\BookingActor;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Domain\Bookings\Exceptions\IllegalTransition;
use App\Domain\Bookings\Exceptions\InvalidJobOtp;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place a booking status may change (04-Database-Schema integrity
 * rules). Validates the transition, applies side effects, appends the
 * booking_status_history row and fires BookingStatusChanged — atomically.
 */
class BookingStateMachine
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return list<BookingStatus>
     */
    public function allowedFrom(BookingStatus $from): array
    {
        return match ($from) {
            BookingStatus::PendingPayment => [
                BookingStatus::Placed,
                BookingStatus::FailedPayment,
                BookingStatus::CancelledCustomer,
                BookingStatus::Expired,
            ],
            BookingStatus::Placed => [
                BookingStatus::Searching,
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledAdmin,
            ],
            BookingStatus::Searching => [
                BookingStatus::Assigned,
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledAdmin,
                BookingStatus::Expired,
            ],
            BookingStatus::Assigned => [
                BookingStatus::Accepted,
                BookingStatus::Searching, // provider declined / released
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledProvider,
                BookingStatus::CancelledAdmin,
            ],
            BookingStatus::Accepted => [
                BookingStatus::EnRoute,
                BookingStatus::Searching, // reschedule releases the provider
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledProvider,
                BookingStatus::CancelledAdmin,
            ],
            BookingStatus::EnRoute => [
                BookingStatus::Arrived,
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledProvider,
                BookingStatus::CancelledAdmin,
            ],
            BookingStatus::Arrived => [
                BookingStatus::InProgress,
                BookingStatus::CancelledCustomer,
                BookingStatus::CancelledProvider,
                BookingStatus::CancelledAdmin,
            ],
            BookingStatus::InProgress => [
                BookingStatus::Completed,
                BookingStatus::CancelledAdmin,
            ],
            default => [],
        };
    }

    public function canTransition(BookingStatus $from, BookingStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }

    /**
     * Record the booking's very first status (no from-status). The booking
     * row is created by PlaceBooking with its initial status already set;
     * this appends the opening history entry.
     */
    public function initialize(Booking $booking, BookingActor $actor, ?User $actorUser = null): void
    {
        $booking->statusHistory()->create([
            'from_status' => null,
            'to_status' => $booking->status->value,
            'actor_id' => $actorUser?->id,
            'actor_type' => $actor->value,
            'note' => null,
            'created_at' => now(),
        ]);
    }

    public function transition(
        Booking $booking,
        BookingStatus $to,
        BookingActor $actor,
        ?User $actorUser = null,
        ?string $note = null,
        ?string $otp = null,
    ): Booking {
        $from = $booking->status;

        if (! $this->canTransition($from, $to)) {
            throw IllegalTransition::between($from, $to);
        }

        $this->guardAssignment($booking, $to);
        $this->guardJobOtp($booking, $from, $to, $actor, $otp);

        return DB::transaction(function () use ($booking, $from, $to, $actor, $actorUser, $note) {
            $booking->status = $to;

            if ($to === BookingStatus::Searching && in_array($from, [BookingStatus::Assigned, BookingStatus::Accepted], true)) {
                $booking->provider_id = null;
            }

            if ($to === BookingStatus::Completed) {
                $booking->completed_at = now();
            }

            if ($to->isCancellation() || $to === BookingStatus::Expired) {
                $booking->cancelled_at = now();
                $booking->cancelled_by = $actorUser?->id;
                $booking->cancel_reason = $note;
            }

            $booking->save();

            $booking->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_id' => $actorUser?->id,
                'actor_type' => $actor->value,
                'note' => $note,
                'created_at' => now(),
            ]);

            BookingStatusChanged::dispatch($booking, $from, $to, $actor);

            return $booking;
        });
    }

    private function guardAssignment(Booking $booking, BookingStatus $to): void
    {
        if ($to === BookingStatus::Assigned && $booking->provider_id === null) {
            throw new IllegalTransition('A booking cannot be assigned without a provider.');
        }
    }

    /**
     * Job-start OTP (M04, UC parity): with booking.job_otp_required on, the
     * provider must present the customer's 4-digit code to start the job.
     * Admin and system actors bypass the code; the history row still shows
     * who forced the transition.
     */
    private function guardJobOtp(Booking $booking, BookingStatus $from, BookingStatus $to, BookingActor $actor, ?string $otp): void
    {
        if ($from !== BookingStatus::Arrived || $to !== BookingStatus::InProgress) {
            return;
        }

        if (! $this->settings->boolean('booking.job_otp_required', true)) {
            return;
        }

        if ($actor === BookingActor::Admin || $actor === BookingActor::System) {
            return;
        }

        if ($booking->job_otp_code === null || $otp !== $booking->job_otp_code) {
            throw InvalidJobOtp::make();
        }
    }
}
